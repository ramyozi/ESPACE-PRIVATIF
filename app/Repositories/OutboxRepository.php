<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

/**
 * File de messages WebSocket sortants vers SOTHIS.
 * On insere d'abord, le worker emet ensuite. Idempotence via message_id.
 */
final class OutboxRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function enqueue(int $tenantId, string $messageId, string $type, array $payload): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO ws_outbox (tenant_id, message_id, type, payload, status)
             VALUES (:tenant, :mid, :type, :payload, :status)'
        );
        $stmt->execute([
            'tenant' => $tenantId,
            'mid' => $messageId,
            'type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
        ]);
    }

    /**
     * Recupere les messages a transmettre.
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchPending(int $limit = 50): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT * FROM ws_outbox
             WHERE status = 'pending'
             ORDER BY id ASC LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markSent(int $id): void
    {
        $stmt = $this->connection->pdo()->prepare(
            "UPDATE ws_outbox SET status = 'sent', attempts = attempts + 1 WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    public function markAcked(string $messageId): void
    {
        $stmt = $this->connection->pdo()->prepare(
            "UPDATE ws_outbox SET status = 'acked', acked_at = NOW() WHERE message_id = :mid"
        );
        $stmt->execute(['mid' => $messageId]);
    }

    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->connection->pdo()->prepare(
            "UPDATE ws_outbox SET status = 'failed', attempts = attempts + 1, last_error = :err
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id, 'err' => substr($error, 0, 1000)]);
    }
}
