<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\SothisDepositService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Tests unitaires du service de depot SOTHIS.
 * On mocke les repositories pour ne pas dependre de la base.
 */
final class SothisDepositServiceTest extends TestCase
{
    public function testDepositReussiCreeLeDocumentEtTraceLAudit(): void
    {
        $user = $this->buildUser(tenantId: 1, residenceId: 7);

        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->with(1, 1)->willReturn($user);

        $documents = $this->createMock(DocumentRepository::class);
        $documents->method('existsBySothisId')->willReturn(false);
        $documents->expects($this->once())->method('create')
            ->with($this->callback(function (array $data) {
                // On verifie que le service prepare bien le payload attendu
                return $data['tenant_id'] === 1
                    && $data['user_id'] === 1
                    && $data['residence_id'] === 7
                    && $data['title'] === 'Bail locatif'
                    && $data['pdf_path'] === 'https://example.com/file.pdf'
                    && strlen($data['pdf_sha256']) === 64
                    && str_starts_with($data['sothis_document_id'], 'DOC-');
            }))
            ->willReturn(42);

        $audit = $this->createMock(AuditService::class);
        $audit->expects($this->once())->method('log')
            ->with(
                $this->equalTo(1), // tenantId
                $this->equalTo(1), // userId
                $this->equalTo('document_deposit'),
            );

        $service = new SothisDepositService($documents, $users, $audit, new NullLogger());

        $id = $service->deposit(
            tenantId: 1,
            userId: 1,
            documentName: 'Bail locatif',
            pdfUrl: 'https://example.com/file.pdf',
        );

        self::assertSame(42, $id);
    }

    public function testDepositEchoueSiUserPasDansLeTenant(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn(null);

        $documents = $this->createMock(DocumentRepository::class);
        $documents->expects($this->never())->method('create');

        $service = new SothisDepositService(
            $documents,
            $users,
            $this->createMock(AuditService::class),
            new NullLogger(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tenant_or_user_not_found');

        $service->deposit(
            tenantId: 999,
            userId: 1,
            documentName: 'X',
            pdfUrl: 'https://example.com/file.pdf',
        );
    }

    public function testDepositEchoueSiSothisIdDejaPresent(): void
    {
        $user = $this->buildUser(tenantId: 1);

        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn($user);

        $documents = $this->createMock(DocumentRepository::class);
        // On force la collision (cas extreme : meme timestamp + meme random)
        $documents->method('existsBySothisId')->willReturn(true);
        $documents->expects($this->never())->method('create');

        $service = new SothisDepositService(
            $documents,
            $users,
            $this->createMock(AuditService::class),
            new NullLogger(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplicate');

        $service->deposit(1, 1, 'Doc', 'https://example.com/file.pdf');
    }

    private function buildUser(int $tenantId, ?int $residenceId = 1): User
    {
        return new User(
            id: 1,
            tenantId: $tenantId,
            residenceId: $residenceId,
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
