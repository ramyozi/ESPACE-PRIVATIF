<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Tests unitaires de l'AuthService.
 * On mocke UserRepository pour ne pas dependre de la base.
 */
final class AuthServiceTest extends TestCase
{
    public function testLoginOkAvecBonMotDePasse(): void
    {
        $hash = password_hash('demo1234', PASSWORD_BCRYPT, ['cost' => 4]);
        $user = $this->buildUser(passwordHash: $hash);

        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->once())->method('findByEmail')
            ->with('alice@example.test', null)
            ->willReturn($user);
        $repo->expects($this->once())->method('resetFailedLogins')->with($user->id);

        $service = new AuthService($repo, new NullLogger());
        $result = $service->attemptLogin('alice@example.test', 'demo1234');

        self::assertNotNull($result);
        self::assertSame('alice@example.test', $result->email);
    }

    public function testLoginKoMotDePasseFaux(): void
    {
        $hash = password_hash('demo1234', PASSWORD_BCRYPT, ['cost' => 4]);
        $user = $this->buildUser(passwordHash: $hash);

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmail')->willReturn($user);
        $repo->expects($this->once())->method('incrementFailedLogins')->with($user->id);

        $service = new AuthService($repo, new NullLogger());
        $result = $service->attemptLogin('alice@example.test', 'mauvais');

        self::assertNull($result);
    }

    public function testLoginKoEmailInconnu(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmail')->willReturn(null);

        $service = new AuthService($repo, new NullLogger());
        $result = $service->attemptLogin('inconnu@example.test', 'whatever');

        self::assertNull($result);
    }

    public function testLoginKoCompteVerrouille(): void
    {
        $hash = password_hash('demo1234', PASSWORD_BCRYPT, ['cost' => 4]);
        // Verrouillage actif jusqu'a +1h : meme avec bon mot de passe, on refuse
        $user = $this->buildUser(
            passwordHash: $hash,
            lockedUntil: new \DateTimeImmutable('+1 hour'),
        );

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmail')->willReturn($user);

        $service = new AuthService($repo, new NullLogger());
        $result = $service->attemptLogin('alice@example.test', 'demo1234');

        self::assertNull($result);
    }

    public function testVerrouillageDeclencheApresCinqEchecs(): void
    {
        $hash = password_hash('demo1234', PASSWORD_BCRYPT, ['cost' => 4]);
        $user = $this->buildUser(passwordHash: $hash, failedLogins: 4);

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmail')->willReturn($user);
        // Au 5e echec, le service doit verrouiller le compte
        $repo->expects($this->once())->method('incrementFailedLogins');
        $repo->expects($this->once())->method('lockUntil');

        $service = new AuthService($repo, new NullLogger());
        $service->attemptLogin('alice@example.test', 'mauvais');
    }

    private function buildUser(
        string $passwordHash,
        int $failedLogins = 0,
        ?\DateTimeImmutable $lockedUntil = null,
    ): User {
        return new User(
            id: 1,
            tenantId: 1,
            residenceId: 1,
            externalId: 'LOC-1001',
            email: 'alice@example.test',
            firstName: 'Alice',
            lastName: 'Martin',
            passwordHash: $passwordHash,
            failedLogins: $failedLogins,
            lockedUntil: $lockedUntil,
        );
    }
}
