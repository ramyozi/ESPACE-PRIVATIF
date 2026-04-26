<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\OtpRepository;
use App\Services\MailService;
use App\Services\OtpService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service OTP.
 * Mocks complets : on n'a besoin ni de la BDD ni du mail reel.
 */
final class OtpServiceTest extends TestCase
{
    private array $config = [
        'length' => 6,
        'ttl' => 300,
        'maxAttempts' => 3,
    ];

    public function testVerifyEchoueQuandAucunCodeActif(): void
    {
        $repo = $this->createMock(OtpRepository::class);
        $repo->method('findActive')->willReturn(null);

        $mail = $this->createMock(MailService::class);
        $service = new OtpService($repo, $mail, $this->config);

        $result = $service->verify(userId: 1, target: 'signature_doc_1', submittedCode: '123456');
        self::assertFalse($result['ok']);
        self::assertSame('otp_not_found', $result['reason']);
    }

    public function testVerifyEchoueAvecMauvaisCodeEtIncrementeLeCompteur(): void
    {
        $repo = $this->createMock(OtpRepository::class);
        $repo->method('findActive')->willReturn([
            'id' => 42,
            'code_hash' => hash('sha256', '654321'),
            'attempts' => 0,
        ]);
        $repo->expects($this->once())->method('incrementAttempts')->with(42);

        $mail = $this->createMock(MailService::class);
        $service = new OtpService($repo, $mail, $this->config);

        $result = $service->verify(userId: 1, target: 'signature_doc_1', submittedCode: '111111');
        self::assertFalse($result['ok']);
        self::assertSame('otp_invalid', $result['reason']);
    }

    public function testVerifyVerrouilleApresMaxAttempts(): void
    {
        $repo = $this->createMock(OtpRepository::class);
        $repo->method('findActive')->willReturn([
            'id' => 42,
            'code_hash' => hash('sha256', '654321'),
            'attempts' => 3, // deja au max
        ]);

        $mail = $this->createMock(MailService::class);
        $service = new OtpService($repo, $mail, $this->config);

        $result = $service->verify(userId: 1, target: 'signature_doc_1', submittedCode: '654321');
        self::assertFalse($result['ok']);
        self::assertSame('otp_locked', $result['reason']);
    }

    public function testVerifyOkConsommeLeCode(): void
    {
        $repo = $this->createMock(OtpRepository::class);
        $repo->method('findActive')->willReturn([
            'id' => 42,
            'code_hash' => hash('sha256', '654321'),
            'attempts' => 0,
        ]);
        $repo->expects($this->once())->method('markConsumed')->with(42);

        $mail = $this->createMock(MailService::class);
        $service = new OtpService($repo, $mail, $this->config);

        $result = $service->verify(userId: 1, target: 'signature_doc_1', submittedCode: '654321');
        self::assertTrue($result['ok']);
    }

    public function testGenerateEnvoieUnMailEtPersistantLeHash(): void
    {
        $repo = $this->createMock(OtpRepository::class);
        $repo->expects($this->once())->method('invalidatePrevious');
        $repo->expects($this->once())->method('create')
            ->with(
                $this->equalTo(1),
                $this->equalTo(2),
                $this->isType('string'),
                $this->equalTo('signature_doc_99'),
                $this->isInstanceOf(\DateTimeImmutable::class),
            );

        $mail = $this->createMock(MailService::class);
        $mail->expects($this->once())->method('queue');

        $service = new OtpService($repo, $mail, $this->config);
        $code = $service->generateAndSend(
            tenantId: 1,
            userId: 2,
            userEmail: 'alice@example.test',
            target: 'signature_doc_99',
        );

        self::assertSame(6, strlen($code));
        self::assertMatchesRegularExpression('/^\d{6}$/', $code);
    }
}
