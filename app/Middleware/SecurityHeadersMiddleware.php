<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Headers de securite communs poses sur chaque reponse.
 *
 * Cas particulier : la route GET /api/documents/{id}/pdf doit pouvoir etre
 * affichee dans un <iframe> sur le frontend Vercel (preview PDF). On
 * desactive donc X-Frame-Options et on remplace `frame-ancestors 'none'`
 * par la liste blanche d'origines (memes que CORS_ALLOWED_ORIGINS) UNIQUEMENT
 * sur cette route. Tout le reste de l'API reste interdit aux iframes.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $path = $request->getUri()->getPath();
        // Match strict de la route PDF : /api/documents/{id}/pdf
        $isPdfRoute = (bool) preg_match('#^/api/documents/\d+/pdf$#', $path);

        if ($isPdfRoute) {
            // Origines autorisees a embarquer le PDF dans une iframe.
            // 'self' = le domaine de l'API lui-meme, + les origines CORS connues.
            $origins = array_values(array_filter(array_map(
                static fn (string $o): string => rtrim(trim($o), '/'),
                explode(',', (string) ($_ENV['CORS_ALLOWED_ORIGINS'] ?? '')),
            )));
            $frameAncestors = "'self'" . ($origins ? ' ' . implode(' ', $origins) : '');

            $csp = "default-src 'self'; "
                 . "img-src 'self' data:; "
                 . "style-src 'self' 'unsafe-inline'; "
                 . "script-src 'self'; "
                 . "object-src 'none'; "
                 . "base-uri 'self'; "
                 . "frame-ancestors {$frameAncestors}";

            // Pas de X-Frame-Options : il prime sur frame-ancestors et
            // n'accepte ni liste blanche ni multiples origines fiables.
            return $response
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
                ->withHeader('Content-Security-Policy', $csp);
        }

        // CSP standard : strict, pas d'iframe possible sur les autres routes.
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
