<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Demarre la session PHP avec des cookies surs.
 *
 * Local (APP_ENV=dev) : SameSite=Lax + Secure=false
 *   -> compatible http://localhost et meme origine via le proxy Vite.
 *
 * Cloud (APP_ENV=prod) : SameSite=None + Secure=true
 *   -> requis pour que le cookie soit envoye par le navigateur lorsque
 *      le frontend (Vercel) et l'API (Render) sont sur des domaines
 *      differents (cross-origin avec credentials).
 *
 * HttpOnly est conserve dans tous les cas pour bloquer l'acces JS au cookie.
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isProd = ($_ENV['APP_ENV'] ?? 'prod') === 'prod';

            session_set_cookie_params([
                'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
                'path' => '/',
                // En prod, le cookie DOIT etre Secure (HTTPS only) puisque
                // SameSite=None l'exige cote navigateurs modernes.
                'secure' => $isProd,
                'httponly' => true,
                // None en cross-origin (Vercel <-> Render), Lax en local.
                'samesite' => $isProd ? 'None' : 'Lax',
            ]);
            session_name('EP_SESSID');
            session_start();
        }

        return $handler->handle($request);
    }
}
