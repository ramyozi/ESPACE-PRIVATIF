<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Endpoints d'authentification du locataire.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function login(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        // Le tenant_id est optionnel : si absent, on cherche sur l'email seul
        // (utile en multi-tenant si l'email reste unique dans la pratique).
        $tenantId = isset($body['tenant_id']) ? (int) $body['tenant_id'] : null;

        if ($email === '' || $password === '') {
            return JsonResponse::error($response, 'invalid_input', 'Email et mot de passe requis', 422);
        }

        $user = $this->authService->attemptLogin($email, $password, $tenantId);

        if ($user === null) {
            return JsonResponse::error($response, 'invalid_credentials', 'Identifiants incorrects', 401);
        }

        // Regeneration de l'identifiant de session pour eviter la fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['tenant_id'] = $user->tenantId;
        $_SESSION['logged_in_at'] = time();

        return JsonResponse::ok($response, [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'tenantId' => $user->tenantId,
            ],
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        return JsonResponse::ok($response, ['loggedOut' => true]);
    }

    public function me(Request $request, Response $response): Response
    {
        // L'AuthMiddleware injecte deja l'utilisateur authentifie dans la requete
        $user = $request->getAttribute('user');
        if ($user === null) {
            return JsonResponse::error($response, 'auth_required', 'Authentification requise', 401);
        }

        return JsonResponse::ok($response, [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'tenantId' => $user->tenantId,
            ],
        ]);
    }
}
