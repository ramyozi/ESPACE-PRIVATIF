<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

/**
 * Document soumis a signature.
 */
final class Document
{
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly int $userId,
        public readonly ?int $residenceId,
        public readonly string $sothisDocumentId,
        public readonly string $type,
        public readonly string $title,
        public readonly DocumentState $state,
        public readonly string $pdfPath,
        public readonly string $pdfSha256,
        public readonly ?string $signedPdfPath,
        public readonly ?DateTimeImmutable $deadline,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            tenantId: (int) $row['tenant_id'],
            userId: (int) $row['user_id'],
            residenceId: $row['residence_id'] !== null ? (int) $row['residence_id'] : null,
            sothisDocumentId: (string) $row['sothis_document_id'],
            type: (string) $row['type'],
            title: (string) $row['title'],
            state: DocumentState::from($row['state']),
            pdfPath: (string) $row['pdf_path'],
            pdfSha256: (string) $row['pdf_sha256'],
            signedPdfPath: $row['signed_pdf_path'] ?? null,
            deadline: !empty($row['deadline']) ? new DateTimeImmutable($row['deadline']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }

    public function isOverdue(): bool
    {
        return $this->deadline !== null && $this->deadline < new DateTimeImmutable('now');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sothisDocumentId' => $this->sothisDocumentId,
            'type' => $this->type,
            'title' => $this->title,
            'state' => $this->state->value,
            'deadline' => $this->deadline?->format(DATE_ATOM),
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'hasSignedPdf' => $this->signedPdfPath !== null,
        ];
    }
}
