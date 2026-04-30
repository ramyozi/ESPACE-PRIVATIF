<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MagicLinkRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service de reinitialisation de mot de passe.
 *
 *  - requestReset() : genere un token, persiste son hash, envoie un email
 *  - resetPassword() : verifie le token, met a jour le mot de passe
 *
 * Anti-enumeration : requestReset() ne leve JAMAIS une erreur "user inconnu"
 * pour eviter qu'un attaquant puisse savoir si une adresse existe. On retourne
 * silencieusement comme si tout s'etait bien passe.
 */
final class PasswordResetService
{
    private const PURPOSE = 'reset_password';
    private const TOKEN_TTL_MINUTES = 30;
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserRepository $users,
        private readonly MagicLinkRepository $magicLinks,
        private readonly MailService $mail,
        private readonly LoggerInterface $logger,
        private readonly string $appUrl,
    ) {
    }

    /**
     * Genere un token de reset si l'email correspond a un user, et envoie le mail.
     * Retourne le token en clair (utile pour les tests). Toujours non-bloquant.
     */
    public function requestReset(string $email): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '') return null;

        $user = $this->users->findByEmail($email);
        if ($user === null) {
            // On loggue mais on ne renvoie pas d'erreur a l'utilisateur :
            // anti-enumeration des comptes.
            $this->logger->info('password_reset.email_unknown', ['email' => $email]);
            return null;
        }

        // On invalide les eventuels liens precedents (un seul reset actif a la fois)
        $this->magicLinks->invalidatePrevious($user->id, self::PURPOSE);

        // Token aleatoire 32 octets, transmis en clair par mail, stocke en hash.
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = new DateTimeImmutable('+' . self::TOKEN_TTL_MINUTES . ' minutes');

        $this->magicLinks->create($user->tenantId, $user->id, $hash, self::PURPOSE, $expiresAt);

        // URL de reset cote front. APP_URL doit pointer sur le frontend en prod.
        $resetUrl = rtrim($this->appUrl, '/') . '/reset-password?token=' . $token;

        $this->mail->queue(
            tenantId: $user->tenantId,
            to: $user->email,
            subject: 'Reinitialisation de votre mot de passe',
            template: 'password_reset',
            variables: [
                'resetUrl' => $resetUrl,
                'ttl_minutes' => self::TOKEN_TTL_MINUTES,
            ],
        );

        $this->logger->info('password_reset.requested', ['user_id' => $user->id]);

        return $token;
    }

    /**
     * Consomme un token et change le mot de passe.
     * Codes d'erreur metier : invalid_token, password_too_short, user_not_found.
     */
    public function resetPassword(string $token, string $newPassword): void
    {
        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException('invalid_token');
        }
        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new RuntimeException('password_too_short');
        }

        $hash = hash('sha256', $token);
        $row = $this->magicLinks->findActiveByHash($hash, self::PURPOSE);
        if ($row === null) {
            throw new RuntimeException('invalid_token');
        }

        $userId = (int) $row['user_id'];
        $tenantId = (int) $row['tenant_id'];

        // Verifie que le user existe toujours dans son tenant.
        $user = $this->users->findById($userId, $tenantId);
        if ($user === null) {
            throw new RuntimeException('user_not_found');
        }

        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->users->updateProfile($user->id, null, $passwordHash);

        // Marque le token comme consomme (single use)
        $this->magicLinks->markConsumed((int) $row['id']);

        $this->logger->info('password_reset.completed', ['user_id' => $user->id]);
    }
}
