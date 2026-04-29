<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Models\DocumentState;
use App\Models\User;
use App\Services\SothisDepositService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * Endpoints reserves a l'administrateur.
 *
 * On reutilise SothisDepositService pour la creation : le document entre
 * exactement dans le meme flow qu'un depot SOTHIS (etat en_attente_signature,
 * audit log, etc.). Aucune logique metier dupliquee.
 */
final class AdminController
{
    public function __construct(
        private readonly SothisDepositService $depositService,
    ) {
    }

    /**
     * Cree un document et l'assigne a un user existant du meme tenant.
     *
     * Body JSON :
     *  {
     *    "user_id": 12,
     *    "document_name": "Bail residence Lilas",
     *    "pdf_url": "https://..."
     *  }
     *
     * Le tenant_id est deduit de l'admin connecte (pas pris du body) afin
     * d'eviter qu'un admin ne cree un document chez un autre tenant.
     */
    public function createDocument(Request $request, Response $response): Response
    {
        /** @var User $admin */
        $admin = $request->getAttribute('user');

        $body = (array) ($request->getParsedBody() ?? []);
        $userId = (int) ($body['user_id'] ?? 0);
        $name = trim((string) ($body['document_name'] ?? ''));
        $pdfUrl = trim((string) ($body['pdf_url'] ?? ''));

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

        try {
            $documentId = $this->depositService->deposit(
                tenantId: $admin->tenantId,
                userId: $userId,
                documentName: $name,
                pdfUrl: $pdfUrl,
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

        return JsonResponse::ok(
            $response,
            ['documentId' => $documentId, 'state' => DocumentState::EN_ATTENTE_SIGNATURE->value],
            201,
        );
    }
}
