<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

/**
 * Acces a la table audit_log.
 * Append only : on n'expose volontairement qu'INSERT et SELECT.
 */
final class AuditLogRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function lastHashForTenant(int $tenantId): ?string
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT row_hash FROM audit_log WHERE tenant_id = :t ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['t' => $tenantId]);
        $hash = $stmt->fetchColumn();
        return $hash !== false ? (string) $hash : null;
    }

    public function insert(array $row): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO audit_log
                (tenant_id, user_id, action, target_type, target_id, ip, context, prev_hash, row_hash, created_at)
             VALUES (:tenant, :user, :action, :ttype, :tid, :ip, :context, :prev, :row, :created)'
        );
        $stmt->execute($row);
    }
}
