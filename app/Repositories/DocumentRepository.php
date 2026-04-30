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

    /**
     * Recupere un document par id + user_id, SANS filtrage tenant (le tenant
     * est deduit du document lui-meme). Utilise par l'endpoint PDF qui valide
     * via un token signe contenant uniquement userId + documentId.
     */
    public function findById(int $id, int $userId): ?Document
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT * FROM documents WHERE id = :id AND user_id = :user LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'user' => $userId]);
        $row = $stmt->fetch();
        return $row ? Document::fromRow($row) : null;
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

    /**
     * Insere un nouveau document depose par SOTHIS.
     * Retourne l'id genere par MySQL.
     *
     * @param array{
     *   tenant_id:int, user_id:int, residence_id:?int,
     *   sothis_document_id:string, type:string, title:string,
     *   pdf_path:string, pdf_sha256:string, deadline:?string
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO documents
                (tenant_id, user_id, residence_id, sothis_document_id, type, title,
                 state, pdf_path, pdf_sha256, deadline, created_by)
             VALUES
                (:tenant, :user, :residence, :sothis_id, :type, :title,
                 :state, :pdf_path, :pdf_sha, :deadline, :created_by)'
        );
        $stmt->execute([
            'tenant' => $data['tenant_id'],
            'user' => $data['user_id'],
            'residence' => $data['residence_id'] ?? null,
            'sothis_id' => $data['sothis_document_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'state' => DocumentState::EN_ATTENTE_SIGNATURE->value,
            'pdf_path' => $data['pdf_path'],
            'pdf_sha' => $data['pdf_sha256'],
            'deadline' => $data['deadline'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $this->connection->pdo()->lastInsertId();
    }

    /**
     * Verifie si un document avec ce sothis_document_id existe deja pour le tenant.
     * Permet d'eviter les doublons en cas de retry SOTHIS.
     */
    public function existsBySothisId(int $tenantId, string $sothisId): bool
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT 1 FROM documents
             WHERE tenant_id = :tenant AND sothis_document_id = :sid LIMIT 1'
        );
        $stmt->execute(['tenant' => $tenantId, 'sid' => $sothisId]);
        return (bool) $stmt->fetchColumn();
    }

    public function updateState(int $id, DocumentState $state): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE documents SET state = :state, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['state' => $state->value, 'id' => $id]);
    }

    /**
     * Retrouve un document a partir de son identifiant SOTHIS (cle metier).
     * Utile pour traiter les messages "document.finalized" recus en WebSocket.
     */
    public function findBySothisId(string $sothisId): ?\App\Models\Document
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT * FROM documents WHERE sothis_document_id = :sid LIMIT 1'
        );
        $stmt->execute(['sid' => $sothisId]);
        $row = $stmt->fetch();
        return $row ? \App\Models\Document::fromRow($row) : null;
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
