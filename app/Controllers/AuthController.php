<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Repositories\UserRepository;
use App\Security\CsrfTokenManager;
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
        private readonly CsrfTokenManager $csrf,
    ) {
    }

    /**
     * Expose un token CSRF reutilisable pour les requetes mutantes
     * (logout, sign/start, sign/complete, refuse).
     */
    public function csrfToken(Request $request, Response $response): Response
    {
        return JsonResponse::ok($response, [
            'csrfToken' => $this->csrf->getOrCreate(),
        ]);
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

        // On rote le token CSRF a chaque connexion pour eviter la reutilisation
        // d'un eventuel token capture avant l'authentification.
        $csrfToken = $this->csrf->rotate();

        return JsonResponse::ok($response, [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'tenantId' => $user->tenantId,
            ],
            'csrfToken' => $csrfToken,
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

    /**
     * Mise a jour du profil de l'utilisateur connecte.
     *
     * Body JSON :
     *   {
     *     "currentPassword": "...",   // requis pour valider l'identite
     *     "email": "new@example.com", // optionnel
     *     "newPassword": "..."        // optionnel (min 8 caracteres)
     *   }
     *
     * Le mot de passe courant est exige pour toute modification, meme un
     * simple changement d'email. Sur changement d'email, on verifie qu'il
     * n'est pas deja pris dans le tenant.
     */
    public function updateProfile(Request $request, Response $response): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->getAttribute('user');
        if ($user === null) {
            return JsonResponse::error($response, 'auth_required', 'Authentification requise', 401);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $currentPassword = (string) ($body['currentPassword'] ?? '');
        $newEmailRaw = $body['email'] ?? null;
        $newPasswordRaw = $body['newPassword'] ?? null;

        if ($currentPassword === '') {
            return JsonResponse::error($response, 'invalid_input', 'Mot de passe actuel requis', 422);
        }

        // Validation identite
        if ($user->passwordHash === null || !password_verify($currentPassword, $user->passwordHash)) {
            return JsonResponse::error($response, 'invalid_credentials', 'Mot de passe actuel incorrect', 401);
        }

        $emailToSet = null;
        if (is_string($newEmailRaw) && trim($newEmailRaw) !== '') {
            $email = strtolower(trim($newEmailRaw));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return JsonResponse::error($response, 'invalid_input', 'Email invalide', 422);
            }
            if ($email !== strtolower($user->email)) {
                if ($this->userRepository->emailTakenByOther($user->tenantId, $email, $user->id)) {
                    return JsonResponse::error($response, 'email_taken', 'Cet email est deja utilise', 409);
                }
                $emailToSet = $email;
            }
        }

        $passwordToSet = null;
        if (is_string($newPasswordRaw) && $newPasswordRaw !== '') {
            if (strlen($newPasswordRaw) < 8) {
                return JsonResponse::error($response, 'invalid_input', 'Mot de passe trop court (8 caracteres min)', 422);
            }
            $passwordToSet = password_hash($newPasswordRaw, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if ($emailToSet === null && $passwordToSet === null) {
            return JsonResponse::error($response, 'invalid_input', 'Aucune modification fournie', 422);
        }

        $this->userRepository->updateProfile($user->id, $emailToSet, $passwordToSet);

        // Si l'email a change, on rafraichit aussi la session pour rester coherent.
        return JsonResponse::ok($response, [
            'user' => [
                'id' => $user->id,
                'email' => $emailToSet ?? $user->email,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'tenantId' => $user->tenantId,
            ],
            'updated' => [
                'email' => $emailToSet !== null,
                'password' => $passwordToSet !== null,
            ],
        ]);
    }
}
