<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Service mail.
 *
 * Comportement :
 *  - on empile toujours le mail dans la table mail_queue (trace + relance possible)
 *  - on tente immediatement un envoi SMTP synchrone via Symfony Mailer
 *  - si l'envoi reussit, la ligne queue passe en "sent"
 *  - si l'envoi echoue, la ligne reste "pending" (un worker pourra rejouer)
 *
 * Configuration :
 *  - DSN unique via MAIL_DSN (ex. smtp://user:pass@host:587)
 *  - sinon construit a partir de SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS
 *  - si rien n'est configure ou si DSN = "null://null" : pas d'envoi reel,
 *    juste la mise en file (mode dev). Comportement retro-compatible.
 */
final class MailService
{
    /**
     * @param array{from:string,fromName:string,dsn:string} $config
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
        // 1. Persistance immediate en file (source de verite + audit)
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

        // 2. Tentative d'envoi SMTP synchrone (best effort).
        $dsn = $this->resolveDsn();
        if ($dsn === null || $dsn === 'null://null') {
            // Mode dev sans transport configure : on s'arrete a la mise en file.
            return;
        }

        try {
            $mailer = new \Symfony\Component\Mailer\Mailer(Transport::fromDsn($dsn));
            $email = (new Email())
                ->from(sprintf('%s <%s>', $this->config['fromName'], $this->config['from']))
                ->to($to)
                ->subject($subject)
                ->text($this->renderBody($template, $variables));

            $mailer->send($email);

            $upd = $this->connection->pdo()->prepare(
                "UPDATE mail_queue SET status = 'sent', attempts = attempts + 1, sent_at = "
                . ($this->connection->driver() === 'pgsql' ? 'NOW()' : 'NOW()')
                . ' WHERE id = :id'
            );
            $upd->execute(['id' => $queuedId]);

            $this->logger->info('mail.sent', ['id' => $queuedId, 'to' => $to]);
        } catch (Throwable $e) {
            $this->logger->error('mail.send_failed', [
                'id' => $queuedId,
                'error' => $e->getMessage(),
            ]);
            // On ne propage pas l'exception : le mail reste en file pour rejouer.
        }
    }

    /**
     * Resout le DSN final.
     * Priorite a MAIL_DSN. Sinon recompose a partir des SMTP_* si presents.
     */
    private function resolveDsn(): ?string
    {
        $dsn = $this->config['dsn'] ?? null;
        if (is_string($dsn) && $dsn !== '' && $dsn !== 'null://null') {
            return $dsn;
        }

        $host = $_ENV['SMTP_HOST'] ?? '';
        $port = $_ENV['SMTP_PORT'] ?? '';
        if ($host === '' || $port === '') {
            return $dsn; // null ou null://null : on garde le mode dev
        }

        $user = rawurlencode((string) ($_ENV['SMTP_USER'] ?? ''));
        $pass = rawurlencode((string) ($_ENV['SMTP_PASS'] ?? ''));
        $auth = ($user !== '' || $pass !== '') ? "{$user}:{$pass}@" : '';

        return sprintf('smtp://%s%s:%s', $auth, $host, (string) $port);
    }

    /**
     * Rendu texte minimal des templates (memes corps que bin/mail-worker.php).
     * On reste sur du texte brut pour eviter d'introduire un moteur de template.
     */
    private function renderBody(string $template, array $vars): string
    {
        return match ($template) {
            'otp_signature' => sprintf(
                "Votre code de signature : %s\nValidite : %d minutes.\n",
                (string) ($vars['code'] ?? ''),
                (int) ($vars['ttl_minutes'] ?? 5),
            ),
            'signature_done_locataire' => sprintf(
                "Votre signature du document \"%s\" a bien ete enregistree le %s.\n",
                (string) ($vars['document'] ?? ''),
                (string) ($vars['signedAt'] ?? ''),
            ),
            'signature_done_manager' => sprintf(
                "Le locataire %s a signe le document \"%s\" le %s.\n",
                (string) ($vars['locataire'] ?? ''),
                (string) ($vars['document'] ?? ''),
                (string) ($vars['signedAt'] ?? ''),
            ),
            'signature_finalized' => sprintf(
                "Votre document \"%s\" est disponible signe :\n%s\n",
                (string) ($vars['document'] ?? ''),
                (string) ($vars['pdfUrl'] ?? ''),
            ),
            default => "Notification Espace Privatif\n",
        };
    }
}
