<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Models\Document;
use App\Models\DocumentState;

/**
 * Acces aux documents. Toutes les requetes filtrent par tenant_id et user_id
 * pour empecher tout acces croise (defense en profondeur contre l'IDOR).
 */
final class DocumentRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return Document[]
     */
    public function listForUser(int $tenantId, int $userId): array
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT * FROM documents
             WHERE tenant_id = :tenant AND user_id = :user
             ORDER BY created_at DESC'
        );
        $stmt->execute(['tenant' => $tenantId, 'user' => $userId]);

        return array_map(static fn (array $row) => Document::fromRow($row), $stmt->fetchAll());
    }

    public function findForUser(int $id, int $tenantId, int $userId): ?Document
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT * FROM documents
             WHERE id = :id AND tenant_id = :tenant AND user_id = :user
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant' => $tenantId, 'user' => $userId]);
        $row = $stmt->fetch();

        return $row ? Document::fromRow($row) : null;
    }

    public function updateState(int $id, DocumentState $state): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE documents SET state = :state, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['state' => $state->value, 'id' => $id]);
    }

    public function setSignedPdfPath(int $id, string $path): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE documents SET signed_pdf_path = :path, state = :state, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'path' => $path,
            'state' => DocumentState::SIGNE_VALIDE->value,
            'id' => $id,
        ]);
    }
}
