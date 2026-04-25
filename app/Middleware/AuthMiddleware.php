<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\JsonResponse;
use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * Verifie qu'un locataire est authentifie en session.
 * Si oui, charge l'utilisateur et l'attache a la requete (attribut "user").
 * Sinon, repond 401.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $_SESSION['user_id'] ?? null;
        $tenantId = $_SESSION['tenant_id'] ?? null;

        if (!$userId || !$tenantId) {
            return JsonResponse::error(
                (new ResponseFactory())->createResponse(),
                'auth_required',
                'Authentification requise',
                401,
            );
        }

        $user = $this->userRepository->findById((int) $userId, (int) $tenantId);
        if ($user === null) {
            // L'utilisateur a ete supprime ou le tenant ne correspond plus
            return JsonResponse::error(
                (new ResponseFactory())->createResponse(),
                'auth_invalid',
                'Session invalide',
                401,
            );
        }

        // On expose l'utilisateur authentifie aux controleurs via les attributs PSR-7
        $request = $request->withAttribute('user', $user);
        return $handler->handle($request);
    }
}
