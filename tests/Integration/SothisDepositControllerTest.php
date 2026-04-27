<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controllers\SothisController;
use App\Repositories\DocumentRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\MailService;
use App\Services\SothisDepositService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Tests d'integration du controleur SothisController::deposit.
 *
 * Periode couverte :
 *  - rejet sans header X-API-KEY
 *  - rejet avec mauvaise cle
 *  - validation du payload
 *  - chemin nominal avec service mocke
 */
final class SothisDepositControllerTest extends TestCase
{
    private const API_KEY = 'test-api-key';

    public function testDepositRefuseSansHeader(): void
    {
        $deposit = $this->createMock(SothisDepositService::class);
        $deposit->expects($this->never())->method('deposit');

        $controller = $this->buildController($deposit);
        $request = $this->buildJsonRequest([
            'tenant_id' => 1,
            'user_id' => 1,
            'document_name' => 'X',
            'pdf_url' => 'https://example.com/file.pdf',
        ]);

        $response = $controller->deposit($request, (new ResponseFactory())->createResponse());

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('auth_required', (string) $response->getBody());
    }

    public function testDepositRefuseAvecMauvaiseCle(): void
    {
        $controller = $this->buildController($this->createMock(SothisDepositService::class));
        $request = $this->buildJsonRequest([
            'tenant_id' => 1,
            'user_id' => 1,
            'document_name' => 'X',
            'pdf_url' => 'https://example.com/file.pdf',
        ])->withHeader('X-API-KEY', 'wrong-key');

        $response = $controller->deposit($request, (new ResponseFactory())->createResponse());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testDepositRetourne422SiPayloadIncomplet(): void
    {
        $controller = $this->buildController($this->createMock(SothisDepositService::class));
        $request = $this->buildJsonRequest([
            'tenant_id' => 1,
            // user_id manquant
            'document_name' => 'X',
            'pdf_url' => 'https://example.com/file.pdf',
        ])->withHeader('X-API-KEY', self::API_KEY);

        $response = $controller->deposit($request, (new ResponseFactory())->createResponse());

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('invalid_input', (string) $response->getBody());
    }

    public function testDepositRetourne422SiPdfUrlInvalide(): void
    {
        $controller = $this->buildController($this->createMock(SothisDepositService::class));
        $request = $this->buildJsonRequest([
            'tenant_id' => 1,
            'user_id' => 1,
            'document_name' => 'Bail',
            'pdf_url' => 'pas-une-url',
        ])->withHeader('X-API-KEY', self::API_KEY);

        $response = $controller->deposit($request, (new ResponseFactory())->createResponse());

        self::assertSame(422, $response->getStatusCode());
    }

    public function testDepositReussitAvecCleValide(): void
    {
        $deposit = $this->createMock(SothisDepositService::class);
        $deposit->expects($this->once())->method('deposit')
            ->with(1, 1, 'Bail locatif', 'https://example.com/file.pdf')
            ->willReturn(42);

        $controller = $this->buildController($deposit);
        $request = $this->buildJsonRequest([
            'tenant_id' => 1,
            'user_id' => 1,
            'document_name' => 'Bail locatif',
            'pdf_url' => 'https://example.com/file.pdf',
        ])->withHeader('X-API-KEY', self::API_KEY);

        $response = $controller->deposit($request, (new ResponseFactory())->createResponse());

        self::assertSame(201, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('"documentId":42', $body);
        self::assertStringContainsString('en_attente_signature', $body);
    }

    public function testDepositRetourne404SiUserPasDansTenant(): void
    {
        $deposit = $this->createMock(SothisDepositService::class);
        $deposit->method('deposit')
            ->willThrowException(new RuntimeException('tenant_or_user_not_found'));

        $controller = $this->buildController($deposit);
        $request = $this->buildJsonRequest([
            'tenant_id' => 999,
            'user_id' => 1,
            'document_name' => 'X',
            'pdf_url' => 'https://example.com/file.pdf',
        ])->withHeader('X-API-KEY', self::API_KEY);

        $response = $controller->deposit($request, (new ResponseFactory())->createResponse());
        self::assertSame(404, $response->getStatusCode());
    }

    private function buildController(SothisDepositService $deposit): SothisController
    {
        return new SothisController(
            $this->createMock(DocumentRepository::class),
            $this->createMock(DocumentService::class),
            $this->createMock(UserRepository::class),
            $this->createMock(MailService::class),
            $this->createMock(AuditService::class),
            new NullLogger(),
            $deposit,
            self::API_KEY,
        );
    }

    private function buildJsonRequest(array $body): \Psr\Http\Message\ServerRequestInterface
    {
        $stream = (new StreamFactory())->createStream(json_encode($body));
        return (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/sothis/documents')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream)
            ->withParsedBody($body);
    }
}
