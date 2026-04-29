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

    /**
     * Liste les locataires (role='user') du tenant. Sert au formulaire admin
     * pour selectionner le destinataire d'un document.
     *
     * @return User[]
     */
    public function listTenantsUsers(int $tenantId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            "SELECT * FROM users
             WHERE tenant_id = :tenant AND role = 'user'
             ORDER BY last_name, first_name, email"
        );
        $stmt->execute(['tenant' => $tenantId]);
        $rows = $stmt->fetchAll();
        return array_map(static fn (array $r) => User::fromRow($r), $rows);
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

    /**
     * Verifie si un email est deja utilise dans le tenant donne par un AUTRE
     * utilisateur que celui specifie. Sert a detecter les doublons lors d'un
     * changement d'email.
     */
    public function emailTakenByOther(int $tenantId, string $email, int $excludeUserId): bool
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT 1 FROM users
             WHERE tenant_id = :tenant AND email = :email AND id <> :id
             LIMIT 1'
        );
        $stmt->execute(['tenant' => $tenantId, 'email' => $email, 'id' => $excludeUserId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Met a jour l'email et/ou le hash du mot de passe d'un utilisateur.
     * On accepte des valeurs nulles pour ne pas modifier un champ donne.
     */
    public function updateProfile(int $id, ?string $email = null, ?string $passwordHash = null): void
    {
        $sets = [];
        $params = ['id' => $id];

        if ($email !== null) {
            $sets[] = 'email = :email';
            $params['email'] = $email;
        }
        if ($passwordHash !== null) {
            $sets[] = 'password_hash = :pwd';
            $params['pwd'] = $passwordHash;
        }
        if ($sets === []) {
            return;
        }

        $stmt = $this->connection->pdo()->prepare(
            'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
    }
}
