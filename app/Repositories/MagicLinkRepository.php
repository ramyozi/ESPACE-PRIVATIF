<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use DateTimeImmutable;

/**
 * Acces a la table magic_links.
 * Sert pour les liens de connexion par mail ET pour le reset de mot de passe
 * (purpose = "reset_password"). Le token n'est jamais stocke en clair :
 * seul son SHA-256 est conserve.
 */
final class MagicLinkRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Cree un lien magique pour un user, retourne l'id genere.
     */
    public function create(
        int $tenantId,
        int $userId,
        string $tokenHash,
        string $purpose,
        DateTimeImmutable $expiresAt,
    ): int {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO magic_links (tenant_id, user_id, token_hash, purpose, expires_at)
             VALUES (:tenant, :user, :hash, :purpose, :expires)'
        );
        $stmt->execute([
            'tenant' => $tenantId,
            'user' => $userId,
            'hash' => $tokenHash,
            'purpose' => $purpose,
            'expires' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
        return (int) $this->connection->pdo()->lastInsertId();
    }

    /**
     * Recupere un lien valide (non consomme et non expire) par hash + purpose.
     * Retourne la ligne complete ou null si introuvable.
     */
    public function findActiveByHash(string $tokenHash, string $purpose): ?array
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT * FROM magic_links
             WHERE token_hash = :hash AND purpose = :purpose
               AND consumed_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['hash' => $tokenHash, 'purpose' => $purpose]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Marque le lien comme consomme (single use).
     */
    public function markConsumed(int $id): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE magic_links SET consumed_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Annule tous les liens encore actifs d'un user pour un purpose donne.
     * Appele avant d'emettre un nouveau lien pour eviter qu'un ancien
     * mail de reset reste exploitable.
     */
    public function invalidatePrevious(int $userId, string $purpose): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE magic_links SET consumed_at = NOW()
             WHERE user_id = :user AND purpose = :purpose AND consumed_at IS NULL'
        );
        $stmt->execute(['user' => $userId, 'purpose' => $purpose]);
    }
}
