<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OtpRepository;
use DateTimeImmutable;

/**
 * Generation et verification des codes OTP a 6 chiffres.
 * Le code envoye au locataire est genere ici, on ne le stocke jamais en clair.
 */
final class OtpService
{
    /**
     * @param array{length:int,ttl:int,maxAttempts:int} $config
     */
    public function __construct(
        private readonly OtpRepository $repository,
        private readonly MailService $mailService,
        private readonly array $config,
    ) {
    }

    /**
     * Genere un nouveau code, l'enregistre, et l'envoie par mail.
     * Retourne true si l'envoi a ete mis en file.
     */
    public function generateAndSend(
        int $tenantId,
        int $userId,
        string $userEmail,
        string $target,
    ): string {
        // On annule tout code precedent encore actif sur la meme cible
        $this->repository->invalidatePrevious($userId, $target);

        $code = $this->generateCode($this->config['length']);
        $hash = hash('sha256', $code);
        $expiresAt = new DateTimeImmutable('+' . $this->config['ttl'] . ' seconds');

        $this->repository->create($tenantId, $userId, $hash, $target, $expiresAt);

        // Mail au locataire avec le code en clair (le seul moment ou il existe en clair)
        $this->mailService->queue(
            tenantId: $tenantId,
            to: $userEmail,
            subject: 'Votre code de signature',
            template: 'otp_signature',
            variables: ['code' => $code, 'ttl_minutes' => (int) ceil($this->config['ttl'] / 60)],
        );

        return $code;
    }

    /**
     * Verifie un code soumis par l'utilisateur.
     * Retourne ['ok' => bool, 'reason' => string|null].
     */
    public function verify(int $userId, string $target, string $submittedCode): array
    {
        $row = $this->repository->findActive($userId, $target);
        if ($row === null) {
            return ['ok' => false, 'reason' => 'otp_not_found'];
        }

        if ((int) $row['attempts'] >= $this->config['maxAttempts']) {
            return ['ok' => false, 'reason' => 'otp_locked'];
        }

        $expected = $row['code_hash'];
        $given = hash('sha256', trim($submittedCode));

        if (!hash_equals($expected, $given)) {
            $this->repository->incrementAttempts((int) $row['id']);
            return ['ok' => false, 'reason' => 'otp_invalid'];
        }

        $this->repository->markConsumed((int) $row['id']);
        return ['ok' => true, 'reason' => null];
    }

    private function generateCode(int $length): string
    {
        $max = (int) str_pad('9', $length, '9');
        $value = random_int(0, $max);
        return str_pad((string) $value, $length, '0', STR_PAD_LEFT);
    }
}
