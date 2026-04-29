<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentRepository;
use App\Repositories\UserRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service de depot d'un document par SOTHIS.
 *
 * Responsabilites :
 *  - valider que le couple (tenant, user) existe vraiment
 *  - generer un identifiant SOTHIS si non fourni (pour rester idempotent)
 *  - inserer le document en etat "en_attente_signature"
 *  - tracer dans l'audit log
 */
final class SothisDepositService
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly UserRepository $users,
        private readonly AuditService $audit,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Cree un nouveau document soumis a signature.
     *
     * @return int Id interne du document cree
     * @throws RuntimeException avec un code metier (tenant_or_user_not_found, duplicate)
     */
    public function deposit(
        int $tenantId,
        int $userId,
        string $documentName,
        string $pdfUrl,
        ?string $type = null,
        ?\DateTimeImmutable $deadline = null,
        ?int $createdBy = null,
    ): int {
        // 1. Verifie que l'utilisateur cible appartient bien au tenant declare.
        //    Cela couvre a la fois "tenant inconnu" et "user pas dans ce tenant".
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            throw new RuntimeException('tenant_or_user_not_found');
        }

        // 2. Genere un identifiant metier SOTHIS deterministe-ish.
        //    Combinaison timestamp + random pour rester unique au sein d'un tenant.
        $sothisDocumentId = sprintf('DOC-%s-%s', date('Ymd-His'), bin2hex(random_bytes(3)));

        // 3. Verification de non-doublon (peu probable mais defensif).
        if ($this->documents->existsBySothisId($tenantId, $sothisDocumentId)) {
            throw new RuntimeException('duplicate');
        }

        // 4. On stocke un hash de l'URL : le SHA reel du fichier sera mis a jour
        //    plus tard si SOTHIS le pousse via un autre flux. Le schema requiert
        //    un CHAR(64) NOT NULL, on respecte la contrainte.
        $urlHash = hash('sha256', $pdfUrl);

        $documentId = $this->documents->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'residence_id' => $user->residenceId,
            'sothis_document_id' => $sothisDocumentId,
            'type' => $type !== null && $type !== '' ? $type : 'document',
            'title' => $documentName,
            'pdf_path' => $pdfUrl,
            'pdf_sha256' => $urlHash,
            'deadline' => $deadline?->format('Y-m-d H:i:s'),
            'created_by' => $createdBy,
        ]);

        // 5. Audit : evenement immuable, lie au tenant et a l'utilisateur cible.
        $this->audit->log(
            tenantId: $tenantId,
            userId: $userId,
            action: 'document_deposit',
            targetType: 'document',
            targetId: (string) $documentId,
            context: [
                'sothis_document_id' => $sothisDocumentId,
                'title' => $documentName,
                'pdf_url' => $pdfUrl,
            ],
        );

        $this->logger->info('sothis.document_deposit', [
            'document_id' => $documentId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        return $documentId;
    }
}
