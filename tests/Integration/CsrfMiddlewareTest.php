<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Middleware\CsrfMiddleware;
use App\Security\CsrfTokenManager;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Tests d'integration du middleware CSRF.
 */
final class CsrfMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testRejette403SansToken(): void
    {
        $manager = new CsrfTokenManager();
        $manager->getOrCreate();

        $middleware = new CsrfMiddleware($manager);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/documents/1/sign/start');

        $response = $middleware->process($request, $this->okHandler());

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('csrf_invalid', (string) $response->getBody());
    }

    public function testAccepteAvecHeaderXCsrfTokenValide(): void
    {
        $manager = new CsrfTokenManager();
        $token = $manager->getOrCreate();

        $middleware = new CsrfMiddleware($manager);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/documents/1/sign/start')
            ->withHeader('X-CSRF-Token', $token);

        $response = $middleware->process($request, $this->okHandler());
        self::assertSame(204, $response->getStatusCode());
    }

    public function testLaisseLesGetPasser(): void
    {
        $manager = new CsrfTokenManager();
        // Pas de token genere : on verifie que la methode GET n'est pas controlee
        $middleware = new CsrfMiddleware($manager);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/documents');

        $response = $middleware->process($request, $this->okHandler());
        self::assertSame(204, $response->getStatusCode());
    }

    private function okHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(204);
            }
        };
    }
}
