<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

/**
 * Service d'authentification du locataire.
 * Regles :
 *  - 5 echecs successifs => verrouillage 15 minutes
 *  - Pas de message d'erreur differencie (pas d'oracle email/mot de passe)
 */
final class AuthService
{
    private const MAX_FAILED_BEFORE_LOCK = 5;
    private const LOCK_DURATION_MIN = 15;

    public function __construct(
        private readonly UserRepository $users,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Tente une authentification par email/mot de passe.
     * Retourne le User si les identifiants sont valides, null sinon.
     */
    public function attemptLogin(string $email, string $password, ?int $tenantId = null): ?User
    {
        $email = strtolower(trim($email));
        $user = $this->users->findByEmail($email, $tenantId);

        if (!$user) {
            // Petit delai constant pour limiter le timing attack
            usleep(150_000);
            return null;
        }

        if ($user->isLocked()) {
            $this->logger->warning('login.locked', ['user_id' => $user->id]);
            return null;
        }

        if ($user->passwordHash === null || !password_verify($password, $user->passwordHash)) {
            $this->users->incrementFailedLogins($user->id);

            // Verrouillage si depassement du seuil
            if ($user->failedLogins + 1 >= self::MAX_FAILED_BEFORE_LOCK) {
                $until = new DateTimeImmutable('+' . self::LOCK_DURATION_MIN . ' minutes');
                $this->users->lockUntil($user->id, $until);
                $this->logger->warning('login.lock_triggered', ['user_id' => $user->id]);
            }

            return null;
        }

        // Reset des compteurs sur connexion reussie
        $this->users->resetFailedLogins($user->id);
        return $user;
    }
}
