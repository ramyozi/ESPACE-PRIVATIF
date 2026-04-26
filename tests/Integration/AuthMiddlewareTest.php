<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Middleware\AuthMiddleware;
use App\Models\User;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Tests d'integration legers du middleware d'authentification.
 *
 * On simule la session via $_SESSION sans demarrer reellement session_start()
 * (le middleware lit directement $_SESSION en interne).
 */
final class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testRefuseAccesSansSession(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $middleware = new AuthMiddleware($repo);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/documents');
        $handler = $this->failingHandler();

        $response = $middleware->process($request, $handler);

        self::assertSame(401, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('auth_required', $body);
    }

    public function testAutoriseLAccesAvecSessionValide(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['tenant_id'] = 1;

        $user = new User(
            id: 1,
            tenantId: 1,
            residenceId: 1,
            externalId: 'LOC-1001',
            email: 'alice@example.test',
            firstName: 'Alice',
            lastName: 'Martin',
            passwordHash: null,
            failedLogins: 0,
            lockedUntil: null,
        );

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->with(1, 1)->willReturn($user);

        $middleware = new AuthMiddleware($repo);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/documents');

        // Le handler doit recevoir la requete enrichie de l'attribut "user"
        $handler = new class implements RequestHandlerInterface {
            public ?User $captured = null;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request->getAttribute('user');
                return (new ResponseFactory())->createResponse(204);
            }
        };

        $response = $middleware->process($request, $handler);

        self::assertSame(204, $response->getStatusCode());
        self::assertNotNull($handler->captured);
        self::assertSame('alice@example.test', $handler->captured->email);
    }

    /**
     * Handler qui ne doit jamais etre appele : utile pour tester un rejet.
     */
    private function failingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('Le handler ne devrait pas etre appele');
            }
        };
    }
}
