<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Models\User;
use App\Services\DocumentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Stream;

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

    /**
     * Streame le PDF associe au document si l'utilisateur connecte y a droit.
     *
     * Securite :
     *  - tenant + user verifies via DocumentService::getForUser
     *  - on n'expose JAMAIS le path brut : on streame le binaire
     *  - resolution stricte du chemin sous storage/pdfs/ pour empecher
     *    toute traversee de repertoire (../ etc.)
     *  - si le pdf_path stocke est une URL externe (cas SOTHIS), on ne sert
     *    rien depuis ce controleur (404) : c'est le frontend qui s'en charge.
     */
    public function downloadPdf(Request $request, Response $response, array $args): Response
    {
        /** @var User $user */
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);

        $document = $this->documentService->getForUser($id, $user->tenantId, $user->id);
        if ($document === null) {
            return JsonResponse::error($response, 'document_not_found', 'Document introuvable', 404);
        }

        $rawPath = $document->pdfPath;
        // URL externe : on n'agit pas en proxy ici (le client peut suivre l'URL).
        if (preg_match('#^https?://#i', $rawPath) === 1) {
            return JsonResponse::error($response, 'remote_pdf', 'PDF distant non servi par cet endpoint', 404);
        }

        $storageRoot = realpath(__DIR__ . '/../../storage/pdfs');
        if ($storageRoot === false) {
            return JsonResponse::error($response, 'storage_unavailable', 'Stockage indisponible', 500);
        }

        // On accepte les chemins relatifs au repo (storage/pdfs/...) ou au
        // dossier storage/pdfs/ directement. realpath verifie l'existence ET
        // resout les ../ eventuels.
        $candidate = str_starts_with($rawPath, 'storage/pdfs/')
            ? __DIR__ . '/../../' . $rawPath
            : $storageRoot . '/' . ltrim($rawPath, '/');

        $resolved = realpath($candidate);
        if ($resolved === false || !str_starts_with($resolved, $storageRoot . DIRECTORY_SEPARATOR)) {
            // Soit le fichier n'existe pas, soit tentative de path traversal.
            return JsonResponse::error($response, 'pdf_not_found', 'PDF indisponible', 404);
        }

        $stream = fopen($resolved, 'rb');
        if ($stream === false) {
            return JsonResponse::error($response, 'pdf_not_readable', 'PDF illisible', 500);
        }

        // ?download=1 -> attachment (force le telechargement local).
        // sans param      -> inline (utilise par l'iframe de preview).
        $params = $request->getQueryParams();
        $isDownload = !empty($params['download']);
        $disposition = $isDownload ? 'attachment' : 'inline';

        // Nom de fichier propose au navigateur : on prefere le titre metier
        // (slugifie) plutot que le nom interne aleatoire du storage.
        $title = trim($document->title) !== '' ? $document->title : 'document';
        $slug = self::slugify($title);
        $downloadName = $slug . '.pdf';

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', $disposition . '; filename="' . $downloadName . '"')
            ->withHeader('Cache-Control', 'private, max-age=300')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withBody(new Stream($stream));
    }

    /**
     * Slug ASCII safe pour Content-Disposition filename.
     * On garde lettres/chiffres/-_ uniquement (RFC 5987 compatible sans utf-8).
     */
    private static function slugify(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $ascii) ?? '';
        return trim($slug, '-_.') ?: 'document';
    }
}
