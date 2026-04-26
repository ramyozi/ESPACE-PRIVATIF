<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Headers de securite communs poses sur chaque reponse.
 * On reste pragmatique : CSP simple compatible avec un futur front Twig
 * inline et l'aperçu PDF via PDF.js (data: pour les images PNG signees).
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // CSP volontairement simple et stricte sur les origines :
        //  - tout charge depuis self
        //  - styles inline autorises (Twig sans framework CSS lourd)
        //  - images data: pour afficher la signature capturee localement
        //  - aucun objet ni embed
        $csp = "default-src 'self'; "
             . "img-src 'self' data:; "
             . "style-src 'self' 'unsafe-inline'; "
             . "script-src 'self'; "
             . "object-src 'none'; "
             . "base-uri 'self'; "
             . "frame-ancestors 'none'";

        return $response
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->withHeader('Content-Security-Policy', $csp);
    }
}
