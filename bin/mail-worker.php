<?php

declare(strict_types=1);

/**
 * Worker qui depile mail_queue et envoie via Resend.
 *
 * Lancement :
 *   docker-compose exec app php bin/mail-worker.php
 *
 * Rejouable : on reprend uniquement les lignes "pending". Une ligne est
 * marquee "sent" sur succes, "failed" apres le 3e echec consecutif.
 *
 * Aucune dependance SMTP. Si RESEND_API_KEY est absent, le worker s'arrete
 * proprement (mode dev, on laisse les mails en file).
 */

use App\Database\Connection;
use App\Services\MailRenderer;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap-env.php';

$dbConfig = [
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'db',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'name' => $_ENV['DB_NAME'] ?? 'espace_privatif',
    'user' => $_ENV['DB_USER'] ?? 'app',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
];

$pdo = (new Connection($dbConfig))->pdo();

$apiKey = $_ENV['RESEND_API_KEY'] ?? '';
$from = $_ENV['MAIL_FROM'] ?? 'no-reply@realsoft.espace.privatif';
$fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Espace Privatif';

if ($apiKey === '') {
    fwrite(STDERR, "RESEND_API_KEY absent : aucun envoi possible. Sortie.\n");
    exit(0);
}

$resend = Resend::client($apiKey);

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
echo count($rows), " mails a traiter via Resend\n";

foreach ($rows as $row) {
    $variables = json_decode((string) $row['variables'], true) ?? [];
    $template = (string) $row['template'];

    try {
        $resend->emails->send([
            'from' => sprintf('%s <%s>', $fromName, $from),
            'to' => [(string) $row['to_email']],
            'subject' => (string) $row['subject'],
            'html' => MailRenderer::renderHtml($template, $variables),
            'text' => MailRenderer::renderText($template, $variables),
        ]);
        $markSent->execute(['id' => $row['id']]);
        echo "OK  #{$row['id']} -> {$row['to_email']}\n";
    } catch (\Throwable $e) {
        $status = ((int) $row['attempts'] >= 2) ? 'failed' : 'pending';
        $markFailed->execute(['st' => $status, 'id' => $row['id']]);
        echo "ERR #{$row['id']} : ", $e->getMessage(), "\n";
    }
}
