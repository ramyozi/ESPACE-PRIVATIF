<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Demarre la session PHP avec des cookies surs.
 * On reste sur les sessions natives, suffisant pour le perimetre.
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
                'path' => '/',
                'secure' => false, // a passer a true derriere HTTPS
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name('EP_SESSID');
            session_start();
        }

        return $handler->handle($request);
    }
}
