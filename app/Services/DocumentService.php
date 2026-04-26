<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentState;
use App\Repositories\DocumentRepository;
use RuntimeException;

/**
 * Service metier autour des documents.
 * Centralise les transitions d'etat pour qu'aucun controller ne les fasse a la main.
 */
final class DocumentService
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * @return Document[]
     */
    public function listForUser(int $tenantId, int $userId): array
    {
        return $this->documents->listForUser($tenantId, $userId);
    }

    public function getForUser(int $documentId, int $tenantId, int $userId): ?Document
    {
        return $this->documents->findForUser($documentId, $tenantId, $userId);
    }

    /**
     * Marque l'ouverture d'une signature (verrouille le document).
     */
    public function startSignature(Document $document, ?string $ip = null): Document
    {
        if ($document->state === DocumentState::SIGNATURE_EN_COURS) {
            // Idempotent : si deja en cours, on ne fait rien
            return $document;
        }

        if (!$document->state->canTransitionTo(DocumentState::SIGNATURE_EN_COURS)) {
            throw new RuntimeException('invalid_state');
        }

        if ($document->isOverdue()) {
            $this->documents->updateState($document->id, DocumentState::EXPIRE);
            throw new RuntimeException('document_expired');
        }

        $this->documents->updateState($document->id, DocumentState::SIGNATURE_EN_COURS);
        $this->audit->log(
            tenantId: $document->tenantId,
            userId: $document->userId,
            action: 'sign_start',
            targetType: 'document',
            targetId: (string) $document->id,
            ip: $ip,
        );

        return Document::fromRow([
            'id' => $document->id,
            'tenant_id' => $document->tenantId,
            'user_id' => $document->userId,
            'residence_id' => $document->residenceId,
            'sothis_document_id' => $document->sothisDocumentId,
            'type' => $document->type,
            'title' => $document->title,
            'state' => DocumentState::SIGNATURE_EN_COURS->value,
            'pdf_path' => $document->pdfPath,
            'pdf_sha256' => $document->pdfSha256,
            'signed_pdf_path' => $document->signedPdfPath,
            'deadline' => $document->deadline?->format('Y-m-d H:i:s'),
            'created_at' => $document->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markSigned(Document $document, ?string $ip = null): void
    {
        if (!$document->state->canTransitionTo(DocumentState::SIGNE)) {
            throw new RuntimeException('invalid_state');
        }
        $this->documents->updateState($document->id, DocumentState::SIGNE);
        $this->audit->log(
            tenantId: $document->tenantId,
            userId: $document->userId,
            action: 'sign_complete',
            targetType: 'document',
            targetId: (string) $document->id,
            ip: $ip,
        );
    }

    public function refuse(Document $document, ?string $reason, ?string $ip = null): void
    {
        if (!$document->state->canTransitionTo(DocumentState::REFUSE)) {
            throw new RuntimeException('invalid_state');
        }
        $this->documents->updateState($document->id, DocumentState::REFUSE);
        $this->audit->log(
            tenantId: $document->tenantId,
            userId: $document->userId,
            action: 'sign_refuse',
            targetType: 'document',
            targetId: (string) $document->id,
            ip: $ip,
            context: ['reason' => $reason],
        );
    }

    public function markValidated(Document $document, string $signedPdfPath): void
    {
        $this->documents->setSignedPdfPath($document->id, $signedPdfPath);
        $this->audit->log(
            tenantId: $document->tenantId,
            userId: $document->userId,
            action: 'sign_validated',
            targetType: 'document',
            targetId: (string) $document->id,
            context: ['path' => $signedPdfPath],
        );
    }
}
