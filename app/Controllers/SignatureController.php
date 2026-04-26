<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\SignatureService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * Endpoints du processus de signature electronique.
 */
final class SignatureController
{
    public function __construct(
        private readonly DocumentService $documentService,
        private readonly SignatureService $signatureService,
    ) {
    }

    /**
     * Demarre la signature : verrouille le document et envoie l'OTP par mail.
     */
    public function start(Request $request, Response $response, array $args): Response
    {
        /** @var User $user */
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);

        $document = $this->documentService->getForUser($id, $user->tenantId, $user->id);
        if ($document === null) {
            return JsonResponse::error($response, 'document_not_found', 'Document introuvable', 404);
        }

        try {
            $this->signatureService->start($user, $document, $this->ipFrom($request));
        } catch (RuntimeException $e) {
            return JsonResponse::error($response, $e->getMessage(), 'Action impossible sur ce document', 409);
        }

        return JsonResponse::ok($response, [
            'documentId' => $document->id,
            'state' => 'signature_en_cours',
            'otpSent' => true,
        ]);
    }

    /**
     * Termine la signature avec l'image PNG et l'OTP saisi.
     */
    public function complete(Request $request, Response $response, array $args): Response
    {
        /** @var User $user */
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $body = (array) ($request->getParsedBody() ?? []);

        $otp = trim((string) ($body['otp'] ?? ''));
        $image = (string) ($body['signature'] ?? '');

        if ($otp === '' || $image === '') {
            return JsonResponse::error($response, 'invalid_input', 'OTP et signature requis', 422);
        }

        $document = $this->documentService->getForUser($id, $user->tenantId, $user->id);
        if ($document === null) {
            return JsonResponse::error($response, 'document_not_found', 'Document introuvable', 404);
        }

        try {
            $result = $this->signatureService->complete(
                user: $user,
                document: $document,
                otp: $otp,
                signatureBase64: $image,
                ip: $this->ipFrom($request),
                userAgent: $request->getHeaderLine('User-Agent') ?: null,
                managerEmail: '', // simplifie : recupere via la residence dans une iteration future
            );
        } catch (RuntimeException $e) {
            // Les codes metier remontent tels quels pour etre lisibles cote front
            return JsonResponse::error($response, $e->getMessage(), 'Signature impossible', 409);
        }

        return JsonResponse::ok($response, $result);
    }

    public function refuse(Request $request, Response $response, array $args): Response
    {
        /** @var User $user */
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $body = (array) ($request->getParsedBody() ?? []);
        $reason = isset($body['reason']) ? (string) $body['reason'] : null;

        $document = $this->documentService->getForUser($id, $user->tenantId, $user->id);
        if ($document === null) {
            return JsonResponse::error($response, 'document_not_found', 'Document introuvable', 404);
        }

        try {
            $this->signatureService->refuse($user, $document, $reason, $this->ipFrom($request));
        } catch (RuntimeException $e) {
            return JsonResponse::error($response, $e->getMessage(), 'Refus impossible', 409);
        }

        return JsonResponse::ok($response, ['state' => 'refuse']);
    }

    private function ipFrom(Request $request): ?string
    {
        $params = $request->getServerParams();
        return $params['REMOTE_ADDR'] ?? null;
    }
}
