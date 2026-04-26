<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use Psr\Log\LoggerInterface;

/**
 * Service mail simplifie : on empile les mails dans la table mail_queue.
 * Un worker (a ajouter ulterieurement) prend en charge l'envoi reel via
 * Symfony Mailer. Pour la phase de developpement, on log aussi le contenu.
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

        // Trace en log applicatif en environnement de dev (pas le contenu OTP en prod)
        $this->logger->info('mail.queued', [
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
        ]);
    }
}
