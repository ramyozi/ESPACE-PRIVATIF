<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\JsonResponse;
use App\Models\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Verifie que l'utilisateur authentifie possede le role "admin".
 * A monter APRES AuthMiddleware (qui injecte l'attribut "user").
 */
final class AdminMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User || !$user->isAdmin()) {
            return JsonResponse::error(
                (new ResponseFactory())->createResponse(),
                'forbidden',
                'Acces administrateur requis',
                403,
            );
        }
        return $handler->handle($request);
    }
}
