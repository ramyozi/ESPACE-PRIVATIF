<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Services\DocumentService;
use App\Services\PdfAccessTokenService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Stream;

/**
 * Endpoints de consultation des documents pour le locataire connecte.
 */
final class DocumentController
{
    public function __construct(
        private readonly DocumentService $documentService,
        private readonly DocumentRepository $documents,
        private readonly PdfAccessTokenService $pdfAccess,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Racines de fichiers acceptees pour la resolution d'un pdf_path :
     *  - storage/pdfs/  : uploads admin (PdfStorageService::store)
     *  - docs/          : assets de demo embarques dans le repo
     * On essaie chaque racine dans l'ordre, on prend la premiere qui resout.
     */
    private const ALLOWED_ROOTS = ['storage/pdfs', 'docs'];

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
     * Emet un token d'acces court pour le PDF d'un document.
     * Cet endpoint EST protege par session : seul un user authentifie ayant
     * acces au document peut obtenir un token. Le token (60s de validite)
     * est ensuite utilise dans l'URL du PDF pour eviter les soucis de cookie
     * cross-origin (iframe, target="_blank", 3rd-party-cookies bloques).
     */
    public function pdfToken(Request $request, Response $response, array $args): Response
    {
        /** @var User $user */
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);

        $document = $this->documentService->getForUser($id, $user->tenantId, $user->id);
        if ($document === null) {
            return JsonResponse::error($response, 'document_not_found', 'Document introuvable', 404);
        }

        $token = $this->pdfAccess->issue($user->id, $document->id);
        return JsonResponse::ok($response, ['token' => $token, 'expiresIn' => 60]);
    }

    /**
     * Streame le PDF associe au document.
     *
     * Authentification : DEUX modes acceptes pour contourner les blocages
     * de cookies tiers cote navigateur :
     *
     *  1. Token signe en query string (?token=...) : verifie par
     *     PdfAccessTokenService, lie a un (userId, documentId, exp). Sert
     *     pour l'iframe de preview et le bouton telecharger sur Vercel.
     *
     *  2. Session classique (cookie EP_SESSID) si l'attribut "user" est
     *     present dans la requete (cas where AuthMiddleware aurait tourne).
     *     Sert pour les acces directs depuis l'API meme origine.
     *
     * Si aucune methode ne valide -> 401 auth_required.
     *
     * Securite supplementaire :
     *  - tenant + user verifies via DocumentRepository::findForUser
     *  - on n'expose JAMAIS le path brut : on streame le binaire
     *  - resolution stricte du chemin sous storage/pdfs/ (anti path-traversal)
     *  - si pdf_path est une URL externe, on renvoie 404 (pas de proxy)
     */
    public function downloadPdf(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $params = $request->getQueryParams();

        // Resolution de l'identite : token URL (preferentiel) puis session.
        $authUserId = null;
        $authTenantId = null;

        $token = (string) ($params['token'] ?? '');
        if ($token !== '') {
            $payload = $this->pdfAccess->verify($token);
            if ($payload !== null && $payload['documentId'] === $id) {
                // Le token contient le userId mais pas le tenantId : on charge
                // le document via son id seul puis on verifie l'appartenance.
                // Securite : un user d'un autre tenant ne peut pas avoir signe
                // ce token (le payload contient SON userId).
                $document = $this->documents->findById($id, $payload['userId']);
                if ($document !== null) {
                    $authUserId = $payload['userId'];
                    $authTenantId = $document->tenantId;
                }
            }
        }

        // Fallback session si token absent / invalide
        if ($authUserId === null) {
            $user = $request->getAttribute('user');
            if ($user instanceof User) {
                $authUserId = $user->id;
                $authTenantId = $user->tenantId;
            }
        }

        if ($authUserId === null || $authTenantId === null) {
            return JsonResponse::error($response, 'auth_required', 'Authentification requise', 401);
        }

        $document = $this->documentService->getForUser($id, $authTenantId, $authUserId);
        if ($document === null) {
            return JsonResponse::error($response, 'document_not_found', 'Document introuvable', 404);
        }

        $rawPath = $document->pdfPath;
        if (preg_match('#^https?://#i', $rawPath) === 1) {
            return JsonResponse::error($response, 'remote_pdf', 'PDF distant non servi par cet endpoint', 404);
        }

        // Resolution multi-source (anti path-traversal) : on essaie chaque
        // racine autorisee dans l'ordre. La premiere qui contient le fichier
        // gagne. realpath() canonicalise et verifie l'existence.
        $resolved = $this->resolvePdf($rawPath);
        if ($resolved === null) {
            $this->logger->warning('pdf.not_found', [
                'document_id' => $document->id,
                'pdf_path' => $rawPath,
            ]);
            return JsonResponse::error($response, 'pdf_not_found', 'PDF indisponible', 404);
        }

        $stream = fopen($resolved, 'rb');
        if ($stream === false) {
            return JsonResponse::error($response, 'pdf_not_readable', 'PDF illisible', 500);
        }

        $isDownload = !empty($params['download']);
        $disposition = $isDownload ? 'attachment' : 'inline';

        $title = trim($document->title) !== '' ? $document->title : 'document';
        $slug = self::slugify($title);
        $downloadName = $slug . '.pdf';

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', $disposition . '; filename="' . $downloadName . '"')
            ->withHeader('Cache-Control', 'private, max-age=60')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withBody(new Stream($stream));
    }

    /**
     * Resout un pdf_path stocke en BDD vers un chemin absolu sur disque.
     *
     * Strategie :
     *   1. on prefixe la racine projet a chaque candidat possible
     *   2. on teste storage/pdfs/<path>, storage/pdfs<path>, docs/<path>, etc.
     *   3. on accepte aussi un chemin deja absolu sous une racine autorisee
     *   4. realpath() canonicalise et bloque les ../
     *   5. on verifie que le chemin final est BIEN sous une racine autorisee
     *      (anti path-traversal)
     *
     * Retourne null si rien ne resout.
     */
    private function resolvePdf(string $rawPath): ?string
    {
        $projectRoot = realpath(__DIR__ . '/../..');
        if ($projectRoot === false) return null;

        $clean = ltrim($rawPath, '/');           // ex. "demo/Lettre.pdf"
        $candidates = [];

        // Cas 1 : le path stocke commence deja par une racine connue (storage/pdfs/...)
        foreach (self::ALLOWED_ROOTS as $root) {
            if (str_starts_with($clean, $root . '/')) {
                $candidates[] = $projectRoot . '/' . $clean;
            }
        }

        // Cas 2 : on prefixe chaque racine connue (cas seed "/demo/Lettre.pdf"
        // qui doit retomber sur docs/Lettre.pdf via la racine "docs", etc.)
        foreach (self::ALLOWED_ROOTS as $root) {
            $candidates[] = $projectRoot . '/' . $root . '/' . $clean;
        }

        // Cas 3 : on tente aussi le basename pur dans chaque racine
        // (ex. "Lettre.pdf" -> "docs/Lettre.pdf")
        $basename = basename($clean);
        if ($basename !== '' && $basename !== $clean) {
            foreach (self::ALLOWED_ROOTS as $root) {
                $candidates[] = $projectRoot . '/' . $root . '/' . $basename;
            }
        }

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved === false) continue;
            // Verification anti path-traversal : le chemin canonique doit
            // commencer par l'une des racines autorisees.
            foreach (self::ALLOWED_ROOTS as $root) {
                $rootAbs = realpath($projectRoot . '/' . $root);
                if ($rootAbs !== false && str_starts_with($resolved, $rootAbs . DIRECTORY_SEPARATOR)) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    private static function slugify(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $ascii) ?? '';
        return trim($slug, '-_.') ?: 'document';
    }
}
