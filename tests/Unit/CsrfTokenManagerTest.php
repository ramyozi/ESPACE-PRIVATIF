<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\CsrfTokenManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du gestionnaire de token CSRF.
 * On utilise la session PHP via $_SESSION (initialisee a la main pour les tests).
 */
final class CsrfTokenManagerTest extends TestCase
{
    protected function setUp(): void
    {
        // On simule une session ouverte pour eviter session_start() reel pendant les tests
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION = [];
    }

    public function testGetOrCreateGenereUnTokenStable(): void
    {
        $manager = new CsrfTokenManager();
        $first = $manager->getOrCreate();
        $second = $manager->getOrCreate();

        self::assertSame($first, $second, 'Le token doit etre stable tant qu il n est pas tourne');
        self::assertSame(64, strlen($first), 'Token attendu : 32 octets en hex');
    }

    public function testIsValidAccepteLeBonToken(): void
    {
        $manager = new CsrfTokenManager();
        $token = $manager->getOrCreate();
        self::assertTrue($manager->isValid($token));
    }

    public function testIsValidRejetteUnTokenManquantOuFaux(): void
    {
        $manager = new CsrfTokenManager();
        $manager->getOrCreate();

        self::assertFalse($manager->isValid(null));
        self::assertFalse($manager->isValid(''));
        self::assertFalse($manager->isValid('mauvais-token'));
    }

    public function testRotateChangeLeToken(): void
    {
        $manager = new CsrfTokenManager();
        $first = $manager->getOrCreate();
        $rotated = $manager->rotate();

        self::assertNotSame($first, $rotated);
        self::assertFalse($manager->isValid($first));
        self::assertTrue($manager->isValid($rotated));
    }
}
