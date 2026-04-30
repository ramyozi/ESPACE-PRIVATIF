<?php

declare(strict_types=1);

/**
 * Serveur WebSocket Ratchet pour la communication avec SOTHIS.
 *
 * Comportement :
 *  - Toutes les 2 secondes, on lit la file ws_outbox et on emet les messages
 *    "pending" vers les clients connectes (typiquement le mock SOTHIS).
 *  - On accepte en entree :
 *      * "ack"               : marque un message_id comme acquitte
 *      * "document.finalized": le PDF signe final est dispo, on met a jour
 *      * "ping"              : repond "pong"
 *
 * Lancement :
 *   docker-compose up ws
 *   (ou en local : php bin/ws-server.php)
 */

use App\Database\Connection;
use App\Models\DocumentState;
use App\Repositories\AuditLogRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\OutboxRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\MailService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap-env.php';

$logger = new Logger('ws');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../var/log/ws.log', 'info'));

$dbConfig = [
    'host' => $_ENV['DB_HOST'] ?? 'db',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'name' => $_ENV['DB_NAME'] ?? 'espace_privatif',
    'user' => $_ENV['DB_USER'] ?? 'app',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
];

$connection = new Connection($dbConfig);
$outbox = new OutboxRepository($connection);
$documents = new DocumentRepository($connection);
$users = new UserRepository($connection);
$audit = new AuditService(new AuditLogRepository($connection));
$mail = new MailService($connection, [
    'from' => $_ENV['MAIL_FROM'] ?? 'no-reply@realsoft.espace.privatif',
    'fromName' => $_ENV['MAIL_FROM_NAME'] ?? 'Espace Privatif',
    'apiKey' => $_ENV['RESEND_API_KEY'] ?? '',
], $logger);

/**
 * Composant Ratchet : on garde une liste de clients connectes (typiquement SOTHIS).
 */
final class SothisChannel implements MessageComponentInterface
{
    /** @var \SplObjectStorage<ConnectionInterface,int> */
    public \SplObjectStorage $clients;

    public function __construct(
        public OutboxRepository $outbox,
        public DocumentRepository $documents,
        public UserRepository $users,
        public AuditService $audit,
        public MailService $mail,
        public Logger $logger,
    ) {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->logger->info('ws.open', ['count' => count($this->clients)]);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode((string) $msg, true);
        if (!is_array($data) || !isset($data['type'])) {
            return;
        }

        match ($data['type']) {
            'ack' => $this->handleAck($data),
            'document.finalized' => $this->handleFinalized($data),
            'ping' => $from->send((string) json_encode(['type' => 'pong'])),
            default => $this->logger->warning('ws.unknown_type', ['type' => $data['type']]),
        };
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        $this->logger->info('ws.close', ['count' => count($this->clients)]);
    }

    public function onError(ConnectionInterface $conn, \Throwable $e): void
    {
        $this->logger->error('ws.error', ['error' => $e->getMessage()]);
        $conn->close();
    }

    public function broadcast(array $message): void
    {
        $payload = (string) json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        foreach ($this->clients as $client) {
            $client->send($payload);
        }
    }

    private function handleAck(array $data): void
    {
        $messageId = $data['payload']['ack_message_id'] ?? null;
        if ($messageId !== null) {
            $this->outbox->markAcked((string) $messageId);
            $this->logger->info('ws.ack', ['message_id' => $messageId]);
        }
    }

    /**
     * Traite la reception d'un PDF signe finalise par SOTHIS.
     * On met a jour l'etat du document et on prepare le mail final.
     */
    private function handleFinalized(array $data): void
    {
        $payload = $data['payload'] ?? [];
        $sothisDocId = $payload['document_id'] ?? null;
        $pdfUrl = $payload['pdf_url'] ?? null;

        if (!$sothisDocId || !$pdfUrl) {
            $this->logger->warning('ws.finalized_invalid', $payload);
            return;
        }

        $document = $this->documents->findBySothisId((string) $sothisDocId);
        if ($document === null) {
            $this->logger->warning('ws.finalized_unknown_doc', ['sothis_id' => $sothisDocId]);
            return;
        }

        // Transition vers "signe_valide" et stockage du chemin final
        $this->documents->setSignedPdfPath($document->id, (string) $pdfUrl);

        $this->audit->log(
            tenantId: $document->tenantId,
            userId: $document->userId,
            action: 'sign_validated',
            targetType: 'document',
            targetId: (string) $document->id,
            context: ['pdf_url' => $pdfUrl],
        );

        $user = $this->users->findById($document->userId, $document->tenantId);
        if ($user !== null) {
            $this->mail->queue(
                tenantId: $document->tenantId,
                to: $user->email,
                subject: 'Votre document signe est disponible',
                template: 'signature_finalized',
                variables: [
                    'document' => $document->title,
                    'pdfUrl' => $pdfUrl,
                ],
            );
        }

        $this->logger->info('ws.finalized', [
            'document_id' => $document->id,
            'sothis_id' => $sothisDocId,
        ]);
    }
}

$channel = new SothisChannel($outbox, $documents, $users, $audit, $mail, $logger);

$loop = Loop::get();

// Tick toutes les 2 secondes pour vider la file outbox
$loop->addPeriodicTimer(2.0, function () use ($channel, $outbox, $logger): void {
    $messages = $outbox->fetchPending(50);
    if (count($messages) === 0) {
        return;
    }
    foreach ($messages as $row) {
        $envelope = [
            'type' => $row['type'],
            'version' => 1,
            'message_id' => $row['message_id'],
            'tenant_id' => (int) $row['tenant_id'],
            'ts' => date(DATE_ATOM),
            'payload' => json_decode((string) $row['payload'], true) ?? [],
        ];
        $channel->broadcast($envelope);
        $outbox->markSent((int) $row['id']);
        $logger->info('ws.sent', ['message_id' => $row['message_id']]);
    }
});

$host = $_ENV['WS_HOST'] ?? '0.0.0.0';
$port = (int) ($_ENV['WS_PORT'] ?? 8081);

$server = IoServer::factory(new HttpServer(new WsServer($channel)), $port, $host);

$logger->info('ws.start', ['host' => $host, 'port' => $port]);
echo "WS server listening on {$host}:{$port}\n";

$server->run();
