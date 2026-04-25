<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Models\User;

/**
 * Acces aux locataires.
 * On filtre systematiquement par tenant_id pour respecter le multi-tenant.
 */
final class UserRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findByEmail(string $email, ?int $tenantId = null): ?User
    {
        $sql = 'SELECT * FROM users WHERE email = :email';
        $params = ['email' => $email];

        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = :tenant';
            $params['tenant'] = $tenantId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function findById(int $id, int $tenantId): ?User
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT * FROM users WHERE id = :id AND tenant_id = :tenant LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant' => $tenantId]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function incrementFailedLogins(int $id): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE users SET failed_logins = failed_logins + 1 WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function resetFailedLogins(int $id): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function lockUntil(int $id, \DateTimeImmutable $until): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE users SET locked_until = :until WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'until' => $until->format('Y-m-d H:i:s')]);
    }
}
