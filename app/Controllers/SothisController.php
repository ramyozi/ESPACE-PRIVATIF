<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Models\DocumentState;
use App\Repositories\DocumentRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\MailService;
use App\Services\SothisDepositService;
use RuntimeException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Endpoints serveur a serveur consommes par SOTHIS.
 *
 * Pour rester simple, l'authentification est ici une cle API statique
 * passee en header X-Sothis-Key. La cle attendue vient de SOTHIS_API_KEY (.env).
 * Une evolution naturelle serait un JWT signe par tenant.
 */
final class SothisController
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly DocumentService $documentService,
        private readonly UserRepository $users,
        private readonly MailService $mailService,
        private readonly AuditService $audit,
        private readonly LoggerInterface $logger,
        private readonly SothisDepositService $depositService,
        private readonly string $expectedApiKey,
    ) {
    }

    /**
     * Depot d'un nouveau document par SOTHIS.
     *
     * Auth : header X-API-KEY (cle partagee SOTHIS_API_KEY)
     * Body JSON :
     *   {
     *     "tenant_id": 1,
     *     "user_id": 1,
     *     "document_name": "Bail locatif",
     *     "pdf_url": "https://example.com/file.pdf"
     *   }
     */
    public function deposit(Request $request, Response $response): Response
    {
        // Auth dediee a ce nouvel endpoint : header standard X-API-KEY.
        if (!$this->hasValidApiKey($request)) {
            $this->logger->warning('sothis.deposit_auth_failed', [
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
            ]);
            return JsonResponse::error($response, 'auth_required', 'Cle API invalide', 401);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $tenantId = (int) ($body['tenant_id'] ?? 0);
        $userId = (int) ($body['user_id'] ?? 0);
        $name = trim((string) ($body['document_name'] ?? ''));
        $pdfUrl = trim((string) ($body['pdf_url'] ?? ''));

        // Validation basique : tous les champs sont requis.
        if ($tenantId <= 0 || $userId <= 0 || $name === '' || $pdfUrl === '') {
            return JsonResponse::error(
                $response,
                'invalid_input',
                'tenant_id, user_id, document_name et pdf_url sont requis',
                422,
            );
        }
        if (!filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
            return JsonResponse::error($response, 'invalid_input', 'pdf_url invalide', 422);
        }
        if (mb_strlen($name) > 200) {
            return JsonResponse::error($response, 'invalid_input', 'document_name trop long', 422);
        }

        try {
            $documentId = $this->depositService->deposit($tenantId, $userId, $name, $pdfUrl);
        } catch (RuntimeException $e) {
            // Le service expose des codes metier explicites
            $code = $e->getMessage();
            $status = match ($code) {
                'tenant_or_user_not_found' => 404,
                'duplicate' => 409,
                default => 500,
            };
            return JsonResponse::error($response, $code, 'Depot impossible', $status);
        }

        return JsonResponse::ok(
            $response,
            ['documentId' => $documentId, 'state' => DocumentState::EN_ATTENTE_SIGNATURE->value],
            201,
        );
    }

    /**
     * Notification de SOTHIS : un PDF signe finalise est disponible.
     * Met le document en "signe_valide" et envoie le mail final au locataire.
     *
     * Body JSON : { "document_id": "DOC-2026-0001", "pdf_url": "https://..." }
     */
    public function finalized(Request $request, Response $response): Response
    {
        // 1. Authentification simple par cle API (defense en profondeur :
        //    a placer aussi derriere un firewall reseau en prod).
        if (!$this->isAuthenticated($request)) {
            $this->logger->warning('sothis.auth_failed', [
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
            ]);
            return JsonResponse::error($response, 'auth_required', 'Cle API invalide', 401);
        }

        // 2. Validation du payload
        $body = (array) ($request->getParsedBody() ?? []);
        $documentId = trim((string) ($body['document_id'] ?? ''));
        $pdfUrl = trim((string) ($body['pdf_url'] ?? ''));

        if ($documentId === '' || $pdfUrl === '') {
            return JsonResponse::error(
                $response,
                'invalid_input',
                'document_id et pdf_url sont requis',
                422,
            );
        }

        if (!filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
            return JsonResponse::error($response, 'invalid_input', 'pdf_url invalide', 422);
        }

        // 3. Recuperation du document via la cle metier SOTHIS
        $document = $this->documents->findBySothisId($documentId);
        if ($document === null) {
            return JsonResponse::error($response, 'document_not_found', 'Document inconnu', 404);
        }

        // 4. Idempotence : si deja en "signe_valide", on accepte sans rien refaire
        if ($document->state === DocumentState::SIGNE_VALIDE) {
            return JsonResponse::ok($response, [
                'documentId' => $document->id,
                'state' => $document->state->value,
                'idempotent' => true,
            ]);
        }

        // 5. On exige que le document soit deja signe cote locataire
        if ($document->state !== DocumentState::SIGNE) {
            return JsonResponse::error(
                $response,
                'invalid_state',
                'Le document n\'est pas en etat "signe"',
                409,
            );
        }

        // 6. Mise a jour : on reutilise le service metier pour garder la logique au meme endroit
        $this->documentService->markValidated($document, $pdfUrl);

        // 7. Mail final au locataire avec le lien du PDF signe
        $user = $this->users->findById($document->userId, $document->tenantId);
        if ($user !== null) {
            $this->mailService->queue(
                tenantId: $document->tenantId,
                to: $user->email,
                subject: 'Votre document signe est disponible',
                template: 'signature_finalized',
                variables: ['document' => $document->title, 'pdfUrl' => $pdfUrl],
            );
        }

        $this->logger->info('sothis.finalized', [
            'document_id' => $document->id,
            'sothis_id' => $documentId,
        ]);

        return JsonResponse::ok($response, [
            'documentId' => $document->id,
            'state' => DocumentState::SIGNE_VALIDE->value,
        ]);
    }

    private function isAuthenticated(Request $request): bool
    {
        $given = $request->getHeaderLine('X-Sothis-Key');
        if ($given === '' || $this->expectedApiKey === '') {
            return false;
        }
        // Comparaison constante pour eviter le timing attack
        return hash_equals($this->expectedApiKey, $given);
    }

    /**
     * Verifie l'authentification du nouvel endpoint de depot.
     * Utilise le header X-API-KEY (convention plus standard).
     */
    private function hasValidApiKey(Request $request): bool
    {
        $given = $request->getHeaderLine('X-API-KEY');
        if ($given === '' || $this->expectedApiKey === '') {
            return false;
        }
        return hash_equals($this->expectedApiKey, $given);
    }
}
