<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Models\User;
use App\Services\DocumentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Endpoints de consultation des documents pour le locataire connecte.
 */
final class DocumentController
{
    public function __construct(private readonly DocumentService $documentService)
    {
    }

    public function list(Request $request, Response $response): Response
    {
        /** @var User $user */
        $user = $request->getAttribute('user');

        $documents = $this->documentService->listForUser($user->tenantId, $user->id);

        return JsonResponse::ok($response, [
            'items' => array_map(static fn ($d) => $d->toArray(), $documents),
            'count' => count($documents),
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        /** @var User $user */
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);

        $document = $this->documentService->getForUser($id, $user->tenantId, $user->id);
        if ($document === null) {
            return JsonResponse::error($response, 'document_not_found', 'Document introuvable', 404);
        }

        return JsonResponse::ok($response, $document->toArray());
    }
}
