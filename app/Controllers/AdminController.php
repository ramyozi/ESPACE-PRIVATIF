<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Models\DocumentState;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\PdfStorageService;
use App\Services\SothisDepositService;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Endpoints reserves a l'administrateur.
 *
 * On reutilise SothisDepositService pour la creation : le document entre
 * exactement dans le meme flow qu'un depot SOTHIS (etat en_attente_signature,
 * audit log, signature OTP, etc.). Aucune logique metier dupliquee.
 */
final class AdminController
{
    public function __construct(
        private readonly SothisDepositService $depositService,
        private readonly PdfStorageService $pdfStorage,
        private readonly UserRepository $users,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Cree un document depuis une URL PDF (sans upload de fichier).
     *
     * Body JSON : { user_id, document_name, pdf_url, type?, deadline? }
     */
    public function createDocument(Request $request, Response $response): Response
    {
        /** @var User $admin */
        $admin = $request->getAttribute('user');
        $body = (array) ($request->getParsedBody() ?? []);

        $userId = (int) ($body['user_id'] ?? 0);
        $name = trim((string) ($body['document_name'] ?? ''));
        $pdfUrl = trim((string) ($body['pdf_url'] ?? ''));
        $type = isset($body['type']) ? trim((string) $body['type']) : null;
        $deadlineRaw = isset($body['deadline']) ? trim((string) $body['deadline']) : null;

        if ($userId <= 0 || $name === '' || $pdfUrl === '') {
            return JsonResponse::error(
                $response,
                'invalid_input',
                'user_id, document_name et pdf_url sont requis',
                422,
            );
        }
        if (!filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
            return JsonResponse::error($response, 'invalid_input', 'pdf_url invalide', 422);
        }

        $deadline = $this->parseDeadline($deadlineRaw);
        if ($deadlineRaw !== null && $deadlineRaw !== '' && $deadline === null) {
            return JsonResponse::error($response, 'invalid_input', 'deadline invalide (format ISO attendu)', 422);
        }

        return $this->finishCreation($response, $admin, $userId, $name, $pdfUrl, $type, $deadline);
    }

    /**
     * Upload PDF + creation du document.
     *
     * Form-data multipart attendu :
     *   - file          : fichier PDF (max 10 Mo)
     *   - user_id       : id du locataire cible (meme tenant que l'admin)
     *   - document_name : titre du document
     *   - type          : optionnel (ex. "bail", "avenant")
     *   - deadline      : optionnel, ISO 8601
     */
    public function uploadDocument(Request $request, Response $response): Response
    {
        /** @var User $admin */
        $admin = $request->getAttribute('user');

        $params = (array) ($request->getParsedBody() ?? []);
        $files = $request->getUploadedFiles();
        /** @var UploadedFileInterface|null $file */
        $file = $files['file'] ?? null;

        $userId = (int) ($params['user_id'] ?? 0);
        $name = trim((string) ($params['document_name'] ?? ''));
        $type = isset($params['type']) ? trim((string) $params['type']) : null;
        $deadlineRaw = isset($params['deadline']) ? trim((string) $params['deadline']) : null;

        if (!$file instanceof UploadedFileInterface) {
            return JsonResponse::error($response, 'invalid_input', 'Fichier PDF requis (champ "file")', 422);
        }
        if ($userId <= 0 || $name === '') {
            return JsonResponse::error($response, 'invalid_input', 'user_id et document_name requis', 422);
        }

        // Defense en profondeur : on verifie que le user appartient au tenant
        // de l'admin AVANT d'ecrire le fichier sur disque.
        $target = $this->users->findById($userId, $admin->tenantId);
        if ($target === null) {
            return JsonResponse::error($response, 'tenant_or_user_not_found', 'Utilisateur introuvable dans votre tenant', 404);
        }

        $deadline = $this->parseDeadline($deadlineRaw);
        if ($deadlineRaw !== null && $deadlineRaw !== '' && $deadline === null) {
            return JsonResponse::error($response, 'invalid_input', 'deadline invalide (format ISO attendu)', 422);
        }

        try {
            $relativePath = $this->pdfStorage->store($file, $admin->tenantId);
        } catch (InvalidArgumentException $e) {
            $this->logger->info('admin.upload_rejected', [
                'admin_id' => $admin->id,
                'reason' => $e->getMessage(),
            ]);
            return JsonResponse::error($response, $e->getMessage(), 'PDF refuse', 422);
        } catch (RuntimeException $e) {
            $this->logger->error('admin.upload_storage_failed', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);
            return JsonResponse::error($response, 'storage_failed', 'Impossible de stocker le PDF', 500);
        }

        // L'URL a stocker dans documents.pdf_path est le chemin relatif au repo
        // (servi plus tard par un controleur dedie ou via une URL signee).
        return $this->finishCreation($response, $admin, $userId, $name, $relativePath, $type, $deadline);
    }

    /**
     * Liste minimaliste des locataires du tenant pour le selecteur du formulaire.
     */
    public function listUsers(Request $request, Response $response): Response
    {
        /** @var User $admin */
        $admin = $request->getAttribute('user');
        $users = $this->users->listTenantsUsers($admin->tenantId);

        return JsonResponse::ok($response, [
            'items' => array_map(static fn (User $u) => [
                'id' => $u->id,
                'email' => $u->email,
                'firstName' => $u->firstName,
                'lastName' => $u->lastName,
            ], $users),
        ]);
    }

    /**
     * Etape commune aux deux endpoints (URL et upload) : appelle le service
     * de depot SOTHIS pour creer le document avec l'admin comme createdBy.
     */
    private function finishCreation(
        Response $response,
        User $admin,
        int $userId,
        string $name,
        string $pdfUrl,
        ?string $type,
        ?DateTimeImmutable $deadline,
    ): Response {
        try {
            $documentId = $this->depositService->deposit(
                tenantId: $admin->tenantId,
                userId: $userId,
                documentName: $name,
                pdfUrl: $pdfUrl,
                type: $type,
                deadline: $deadline,
                createdBy: $admin->id,
            );
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = match ($code) {
                'tenant_or_user_not_found' => 404,
                'duplicate' => 409,
                default => 500,
            };
            return JsonResponse::error($response, $code, 'Creation impossible', $status);
        }

        $this->logger->info('admin.document_created', [
            'admin_id' => $admin->id,
            'document_id' => $documentId,
            'target_user_id' => $userId,
        ]);

        return JsonResponse::ok(
            $response,
            ['documentId' => $documentId, 'state' => DocumentState::EN_ATTENTE_SIGNATURE->value],
            201,
        );
    }

    private function parseDeadline(?string $raw): ?DateTimeImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (Exception) {
            return null;
        }
    }
}
