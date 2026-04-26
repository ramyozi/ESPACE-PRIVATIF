<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Repositories\OutboxRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Passerelle vers SOTHIS via WebSocket.
 * On n'envoie pas directement : on persiste dans ws_outbox.
 * Le worker WS (bin/ws-server.php ou un consumer) prend le relais.
 */
final class SothisGateway
{
    public function __construct(
        private readonly OutboxRepository $outbox,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function queueSignatureCompleted(
        int $tenantId,
        Document $document,
        User $user,
        DateTimeImmutable $signedAt,
        string $signatureBase64,
        string $signatureSha,
        ?string $ip,
        ?string $userAgent,
    ): void {
        $messageId = Uuid::uuid4()->toString();
        $payload = [
            'document_id' => $document->sothisDocumentId,
            'tenant_user_id' => $user->externalId,
            'signed_at' => $signedAt->format('Y-m-d\TH:i:s.v\Z'),
            'signature_image_b64' => $signatureBase64,
            'signature_image_sha256' => $signatureSha,
            'consent' => [
                'method' => 'otp_email',
                'otp_validated_at' => $signedAt->format(DATE_ATOM),
            ],
            'context' => [
                'ip' => $ip,
                'user_agent' => $userAgent,
            ],
        ];

        $this->outbox->enqueue(
            tenantId: $tenantId,
            messageId: $messageId,
            type: 'signature.completed',
            payload: $payload,
        );

        $this->logger->info('sothis.signature_queued', [
            'message_id' => $messageId,
            'document' => $document->id,
        ]);
    }
}
