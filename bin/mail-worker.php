<?php

declare(strict_types=1);

/**
 * Worker tres simple qui depile la table mail_queue et envoie via Symfony Mailer.
 * En dev, le DSN par defaut est "null://null" : les mails ne sont pas envoyes
 * mais leurs informations sont quand meme tracees en log.
 *
 * Lancement :
 *   docker-compose exec app php bin/mail-worker.php
 *
 * En production reelle, ce worker tournerait en boucle (systemd ou supervisor).
 * Ici on traite un batch puis on quitte, par souci de simplicite.
 */

use App\Database\Connection;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap-env.php';

$dbConfig = [
    'host' => $_ENV['DB_HOST'] ?? 'db',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'name' => $_ENV['DB_NAME'] ?? 'espace_privatif',
    'user' => $_ENV['DB_USER'] ?? 'app',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
];

$pdo = (new Connection($dbConfig))->pdo();

$mailer = new Mailer(Transport::fromDsn($_ENV['MAIL_DSN'] ?? 'null://null'));
$from = $_ENV['MAIL_FROM'] ?? 'no-reply@espace-privatif.local';
$fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Espace Privatif';

$select = $pdo->prepare(
    "SELECT * FROM mail_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 50"
);
$markSent = $pdo->prepare(
    "UPDATE mail_queue SET status = 'sent', attempts = attempts + 1, sent_at = NOW() WHERE id = :id"
);
$markFailed = $pdo->prepare(
    "UPDATE mail_queue SET status = :st, attempts = attempts + 1 WHERE id = :id"
);

$select->execute();
$rows = $select->fetchAll();
echo count($rows), " mails a traiter\n";

foreach ($rows as $row) {
    $variables = json_decode((string) $row['variables'], true) ?? [];
    // Rendu minimal du corps : on assemble un texte simple par template
    $body = renderBody((string) $row['template'], $variables);

    $email = (new Email())
        ->from(sprintf('%s <%s>', $fromName, $from))
        ->to((string) $row['to_email'])
        ->subject((string) $row['subject'])
        ->text($body);

    try {
        $mailer->send($email);
        $markSent->execute(['id' => $row['id']]);
        echo "OK  #{$row['id']} -> {$row['to_email']}\n";
    } catch (\Throwable $e) {
        $status = ((int) $row['attempts'] >= 2) ? 'failed' : 'pending';
        $markFailed->execute(['st' => $status, 'id' => $row['id']]);
        echo "ERR #{$row['id']} : ", $e->getMessage(), "\n";
    }
}

/**
 * Rendu texte minimal des templates connus.
 * On evite Twig ici pour rester sans dependance de fichiers.
 */
function renderBody(string $template, array $vars): string
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
