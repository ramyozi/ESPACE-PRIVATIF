<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Document;
use App\Models\DocumentState;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\SignatureRepository;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\MailService;
use App\Services\OtpService;
use App\Services\SignatureFileService;
use App\Services\SignatureService;
use App\Services\SothisGateway;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Tests du SignatureService :
 *  - start() : declenche bien l'OTP et la transition document
 *  - complete() : echoue avec un mauvais OTP
 */
final class SignatureServiceTest extends TestCase
{
    public function testStartDeclenchLOtpEtLaTransition(): void
    {
        $document = $this->buildDocument(DocumentState::EN_ATTENTE_SIGNATURE);
        $user = $this->buildUser();

        $signatures = $this->createMock(SignatureRepository::class);
        $signatures->method('existsForDocument')->willReturn(false);

        $documentService = $this->createMock(DocumentService::class);
        $documentService->expects($this->once())->method('startSignature')
            ->with($document, $this->anything())
            ->willReturn($document);

        $otp = $this->createMock(OtpService::class);
        $otp->expects($this->once())->method('generateAndSend')
            ->with(
                $this->equalTo($user->tenantId),
                $this->equalTo($user->id),
                $this->equalTo($user->email),
                $this->stringContains('signature_doc_'),
            )
            ->willReturn('123456');

        $service = new SignatureService(
            $signatures,
            $this->createMock(DocumentRepository::class),
            $documentService,
            $otp,
            $this->createMock(MailService::class),
            $this->createMock(SothisGateway::class),
            $this->createMock(AuditService::class),
            new SignatureFileService(),
            new NullLogger(),
        );

        $service->start($user, $document, '127.0.0.1');
    }

    public function testStartRefuseSiDejaSigne(): void
    {
        $document = $this->buildDocument(DocumentState::EN_ATTENTE_SIGNATURE);
        $user = $this->buildUser();

        $signatures = $this->createMock(SignatureRepository::class);
        $signatures->method('existsForDocument')->willReturn(true);

        $service = new SignatureService(
            $signatures,
            $this->createMock(DocumentRepository::class),
            $this->createMock(DocumentService::class),
            $this->createMock(OtpService::class),
            $this->createMock(MailService::class),
            $this->createMock(SothisGateway::class),
            $this->createMock(AuditService::class),
            new SignatureFileService(),
            new NullLogger(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already_signed');
        $service->start($user, $document, null);
    }

    public function testCompleteEchoueAvecMauvaisOtp(): void
    {
        $document = $this->buildDocument(DocumentState::SIGNATURE_EN_COURS);
        $user = $this->buildUser();

        $otp = $this->createMock(OtpService::class);
        $otp->method('verify')->willReturn(['ok' => false, 'reason' => 'otp_invalid']);

        $service = new SignatureService(
            $this->createMock(SignatureRepository::class),
            $this->createMock(DocumentRepository::class),
            $this->createMock(DocumentService::class),
            $otp,
            $this->createMock(MailService::class),
            $this->createMock(SothisGateway::class),
            $this->createMock(AuditService::class),
            new SignatureFileService(),
            new NullLogger(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('otp_invalid');

        $service->complete(
            user: $user,
            document: $document,
            otp: '000000',
            signatureBase64: 'data:image/png;base64,iVBORw0KGgo=',
            ip: '127.0.0.1',
            userAgent: 'phpunit',
        );
    }

    public function testOtpDevShortcutValideLaSignatureEnModeDev(): void
    {
        // On force APP_ENV=dev pour activer le raccourci OTP "123456".
        // Le bloc try/finally garantit qu'on restaure la valeur originale apres le test.
        $previousEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'dev';

        try {
            $document = $this->buildDocument(DocumentState::SIGNATURE_EN_COURS);
            $user = $this->buildUser();

            $signatures = $this->createMock(SignatureRepository::class);
            $signatures->expects($this->once())->method('create')->willReturn(99);

            $documentService = $this->createMock(DocumentService::class);
            $documentService->expects($this->once())->method('markSigned');

            // Cle du test : en mode dev avec "123456", on ne doit PAS appeler verify()
            $otp = $this->createMock(OtpService::class);
            $otp->expects($this->never())->method('verify');

            $sothis = $this->createMock(SothisGateway::class);
            $sothis->expects($this->once())->method('queueSignatureCompleted');

            $mail = $this->createMock(MailService::class);
            $mail->expects($this->atLeastOnce())->method('queue');

            $service = new SignatureService(
                $signatures,
                $this->createMock(DocumentRepository::class),
                $documentService,
                $otp,
                $mail,
                $sothis,
                $this->createMock(AuditService::class),
                new SignatureFileService(),
                new NullLogger(),
            );

            $result = $service->complete(
                user: $user,
                document: $document,
                otp: '123456',
                signatureBase64: $this->validPngDataUrl(),
                ip: '127.0.0.1',
                userAgent: 'phpunit',
            );

            self::assertSame(99, $result['signatureId']);
            self::assertSame('signe', $result['state']);
        } finally {
            if ($previousEnv === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $previousEnv;
            }
        }
    }

    public function testOtpDevShortcutNeFonctionnePasEnProduction(): void
    {
        // Test de non-regression securite : "123456" ne doit pas suffire en prod.
        $previousEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'prod';

        try {
            $document = $this->buildDocument(DocumentState::SIGNATURE_EN_COURS);
            $user = $this->buildUser();

            // En prod, le service OTP DOIT etre interroge meme avec "123456"
            $otp = $this->createMock(OtpService::class);
            $otp->expects($this->once())->method('verify')
                ->willReturn(['ok' => false, 'reason' => 'otp_invalid']);

            $service = new SignatureService(
                $this->createMock(SignatureRepository::class),
                $this->createMock(DocumentRepository::class),
                $this->createMock(DocumentService::class),
                $otp,
                $this->createMock(MailService::class),
                $this->createMock(SothisGateway::class),
                $this->createMock(AuditService::class),
                new SignatureFileService(),
                new NullLogger(),
            );

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('otp_invalid');

            $service->complete(
                user: $user,
                document: $document,
                otp: '123456',
                signatureBase64: $this->validPngDataUrl(),
                ip: null,
                userAgent: null,
            );
        } finally {
            if ($previousEnv === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $previousEnv;
            }
        }
    }

    public function testCompleteEchoueSiDocumentPasEnCoursDeSignature(): void
    {
        $document = $this->buildDocument(DocumentState::EN_ATTENTE_SIGNATURE);
        $user = $this->buildUser();

        $service = new SignatureService(
            $this->createMock(SignatureRepository::class),
            $this->createMock(DocumentRepository::class),
            $this->createMock(DocumentService::class),
            $this->createMock(OtpService::class),
            $this->createMock(MailService::class),
            $this->createMock(SothisGateway::class),
            $this->createMock(AuditService::class),
            new SignatureFileService(),
            new NullLogger(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid_state');

        $service->complete(
            user: $user,
            document: $document,
            otp: '000000',
            signatureBase64: 'data:image/png;base64,iVBORw0KGgo=',
            ip: null,
            userAgent: null,
        );
    }

    /**
     * PNG minimal 1x1 pixel transparent, encode en base64 dans un data URL.
     * Suffisant pour passer la verification de signature magic-bytes du service.
     */
    private function validPngDataUrl(): string
    {
        return 'data:image/png;base64,'
            . 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlE'
            . 'QVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==';
    }

    private function buildDocument(DocumentState $state): Document
    {
        return new Document(
            id: 1,
            tenantId: 1,
            userId: 1,
            residenceId: 1,
            sothisDocumentId: 'DOC-2026-0001',
            type: 'bail',
            title: 'Bail demo',
            state: $state,
            pdfPath: '/storage/pdfs/demo.pdf',
            pdfSha256: str_repeat('a', 64),
            signedPdfPath: null,
            deadline: new DateTimeImmutable('+10 days'),
            createdAt: new DateTimeImmutable('-1 day'),
            updatedAt: new DateTimeImmutable('-1 day'),
        );
    }

    private function buildUser(): User
    {
        return new User(
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
    }
}
