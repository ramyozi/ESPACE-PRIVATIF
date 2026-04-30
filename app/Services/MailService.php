<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use Psr\Log\LoggerInterface;
use Resend;
use Throwable;

/**
 * Service mail.
 *
 * Architecture preservee :
 *  - on empile TOUJOURS le mail dans la table mail_queue (audit + retry)
 *  - on tente immediatement un envoi via l'API Resend (HTTPS)
 *  - si l'envoi reussit, la ligne queue passe en "sent"
 *  - si l'envoi echoue ou si la cle est absente, la ligne reste "pending"
 *    (un worker pourra rejouer plus tard via bin/mail-worker.php)
 *
 * Pourquoi Resend plutot que SMTP :
 *  - une seule variable d'environnement (RESEND_API_KEY)
 *  - DKIM/SPF/DMARC configures cote DNS pour realsoft.espace.privatif
 *  - meilleure deliverability et webhooks dispos
 *
 * Securite :
 *  - on ne logue jamais le contenu OTP, juste la cible et le template
 *  - la cle API n'est jamais exposee cote frontend
 */
final class MailService
{
    /**
     * @param array{apiKey:string,from:string,fromName:string} $config
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly array $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function queue(
        ?int $tenantId,
        string $to,
        string $subject,
        string $template,
        array $variables = [],
    ): void {
        // 1. Persistance immediate en file (source de verite + audit).
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO mail_queue (tenant_id, to_email, subject, template, variables)
             VALUES (:tenant, :to, :subject, :template, :vars)'
        );
        $stmt->execute([
            'tenant' => $tenantId,
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
            'vars' => json_encode($variables, JSON_UNESCAPED_UNICODE),
        ]);
        $queuedId = (int) $this->connection->pdo()->lastInsertId();

        $this->logger->info('mail.queued', [
            'id' => $queuedId,
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
        ]);

        // 2. Tentative d'envoi immediat via Resend (best effort).
        $apiKey = $this->config['apiKey'] ?? '';
        if ($apiKey === '') {
            // Fallback safe : pas de cle => on n'envoie rien, on log et on
            // laisse la ligne en pending pour un futur retry.
            $this->logger->warning('mail.skipped_no_resend_key', [
                'id' => $queuedId,
                'to' => $to,
            ]);
            return;
        }

        try {
            $resend = Resend::client($apiKey);
            $resend->emails->send([
                'from' => sprintf('%s <%s>', $this->config['fromName'], $this->config['from']),
                'to' => [$to],
                'subject' => $subject,
                'html' => MailRenderer::renderHtml($template, $variables),
                'text' => MailRenderer::renderText($template, $variables),
            ]);

            $upd = $this->connection->pdo()->prepare(
                "UPDATE mail_queue SET status = 'sent', attempts = attempts + 1, sent_at = NOW()
                 WHERE id = :id"
            );
            $upd->execute(['id' => $queuedId]);

            $this->logger->info('mail.sent', ['id' => $queuedId, 'to' => $to]);
        } catch (Throwable $e) {
            $this->logger->error('mail.send_failed', [
                'id' => $queuedId,
                'error' => $e->getMessage(),
            ]);
            // Pas de propagation : la ligne reste pending pour rejouer plus tard.
        }
    }
}
