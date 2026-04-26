<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\JsonResponse;
use App\Security\CsrfTokenManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Verifie la presence et la validite d'un token CSRF sur les routes critiques.
 *
 * Le token est attendu dans :
 *  - le header HTTP "X-CSRF-Token" (cas standard)
 *  - le champ "csrf_token" du body JSON (en repli, utile pour Postman)
 *
 * En cas de jeton manquant ou invalide, on repond 403.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly CsrfTokenManager $csrf)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // On ne controle que les methodes mutantes (POST, PUT, PATCH, DELETE)
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $handler->handle($request);
        }

        $token = $request->getHeaderLine('X-CSRF-Token');
        if ($token === '') {
            // Repli sur le body
            $body = (array) ($request->getParsedBody() ?? []);
            $token = isset($body['csrf_token']) ? (string) $body['csrf_token'] : '';
        }

        if (!$this->csrf->isValid($token)) {
            return JsonResponse::error(
                (new ResponseFactory())->createResponse(),
                'csrf_invalid',
                'Jeton CSRF manquant ou invalide',
                403,
            );
        }

        return $handler->handle($request);
    }
}
