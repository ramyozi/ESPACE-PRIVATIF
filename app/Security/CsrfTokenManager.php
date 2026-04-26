<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Gestion d'un jeton CSRF stocke en session.
 *
 * Strategie volontairement simple ("synchroniser un token") :
 *  - on genere un token aleatoire de 32 octets (hex 64) en session a la demande
 *  - le client le recupere via GET /api/auth/csrf-token
 *  - il le renvoie sur les routes critiques via le header X-CSRF-Token
 *    (ou le champ "csrf_token" dans le body JSON, comme repli)
 *  - on compare en temps constant avec la valeur en session
 */
final class CsrfTokenManager
{
    private const SESSION_KEY = 'csrf_token';

    public function getOrCreate(): string
    {
        $this->ensureSessionStarted();
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[self::SESSION_KEY];
    }

    public function isValid(?string $given): bool
    {
        $this->ensureSessionStarted();
        $expected = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($expected) || $expected === '' || !is_string($given) || $given === '') {
            return false;
        }
        return hash_equals($expected, $given);
    }

    /**
     * A appeler apres une operation sensible si on souhaite un token a usage unique.
     * Ici on garde le token le temps de la session pour rester pragmatique.
     */
    public function rotate(): string
    {
        $this->ensureSessionStarted();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return (string) $_SESSION[self::SESSION_KEY];
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
