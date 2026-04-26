<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use DateTimeImmutable;

/**
 * Acces aux signatures effectuees.
 */
final class SignatureRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO signatures
                (tenant_id, document_id, user_id, signature_field_id,
                 image_path, image_sha256, signed_at, ip, user_agent,
                 consent_method, consent_proof)
             VALUES
                (:tenant, :document, :user, :field,
                 :path, :sha, :signed, :ip, :ua,
                 :method, :proof)'
        );
        $stmt->execute($data);
        return (int) $this->connection->pdo()->lastInsertId();
    }

    public function existsForDocument(int $documentId): bool
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT 1 FROM signatures WHERE document_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $documentId]);
        return (bool) $stmt->fetchColumn();
    }
}
