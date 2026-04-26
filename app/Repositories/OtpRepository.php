<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

/**
 * Acces aux codes OTP. Les codes ne sont jamais stockes en clair :
 * on enregistre uniquement leur SHA-256.
 */
final class OtpRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(int $tenantId, int $userId, string $codeHash, string $target, \DateTimeImmutable $expiresAt): int
    {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO otp_codes (tenant_id, user_id, code_hash, target, expires_at)
             VALUES (:tenant, :user, :hash, :target, :expires)'
        );
        $stmt->execute([
            'tenant' => $tenantId,
            'user' => $userId,
            'hash' => $codeHash,
            'target' => $target,
            'expires' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
        return (int) $this->connection->pdo()->lastInsertId();
    }

    /**
     * Recupere le code actif (non consomme et non expire) pour un user/cible donne.
     */
    public function findActive(int $userId, string $target): ?array
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT * FROM otp_codes
             WHERE user_id = :user AND target = :target
               AND consumed_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['user' => $userId, 'target' => $target]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function incrementAttempts(int $id): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function markConsumed(int $id): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Annule tous les codes encore actifs pour un user/target.
     * Appele avant la creation d'un nouveau code afin d'eviter les doublons.
     */
    public function invalidatePrevious(int $userId, string $target): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE otp_codes SET consumed_at = NOW()
             WHERE user_id = :user AND target = :target AND consumed_at IS NULL'
        );
        $stmt->execute(['user' => $userId, 'target' => $target]);
    }
}
