<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Middleware CORS pour le deploiement cloud (frontend Vercel + API Render).
 *
 * Lit la liste blanche d'origines depuis CORS_ALLOWED_ORIGINS (separees par
 * virgule). Si vide, aucun header CORS n'est ajoute (comportement local
 * inchange : meme origine via le proxy Vite).
 *
 * Particularites :
 *  - Allow-Credentials = true pour permettre l'envoi des cookies de session
 *    cross-origin (requis par fetch + credentials: 'include').
 *  - Pre-vol OPTIONS : on repond 204 immediatement avec les headers attendus.
 *  - Vary: Origin pour eviter qu'un cache CDN ne reponde la meme chose
 *    a toutes les origines.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private readonly array $allowedOrigins;

    public function __construct(string $allowedOriginsCsv)
    {
        // Parse la liste : "https://a.com,https://b.com" -> [...]
        $this->allowedOrigins = array_values(array_filter(array_map(
            'trim',
            explode(',', $allowedOriginsCsv),
        )));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $isPreflight = strtoupper($request->getMethod()) === 'OPTIONS';

        // Si aucune origine autorisee n'est configuree, on ne fait rien.
        // (mode local : le proxy Vite fait que tout est meme origine).
        if ($this->allowedOrigins === [] || $origin === '') {
            return $isPreflight
                ? (new ResponseFactory())->createResponse(204)
                : $handler->handle($request);
        }

        $isAllowed = in_array($origin, $this->allowedOrigins, true);

        // Pour le pre-vol, on repond directement sans laisser passer la requete
        // au handler (sinon on declenche inutilement la logique d'auth).
        if ($isPreflight) {
            $response = (new ResponseFactory())->createResponse(204);
            return $this->withCorsHeaders($response, $origin, $isAllowed, true);
        }

        $response = $handler->handle($request);
        return $this->withCorsHeaders($response, $origin, $isAllowed, false);
    }

    private function withCorsHeaders(
        ResponseInterface $response,
        string $origin,
        bool $isAllowed,
        bool $isPreflight,
    ): ResponseInterface {
        // Vary: Origin meme si l'origine n'est pas autorisee, pour
        // que le cache ne serve pas une mauvaise reponse.
        $response = $response->withHeader('Vary', 'Origin');

        if (!$isAllowed) {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true');

        if ($isPreflight) {
            $response = $response
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, X-CSRF-Token, X-API-KEY, X-Sothis-Key, Authorization')
                ->withHeader('Access-Control-Max-Age', '600');
        }

        return $response;
    }
}
