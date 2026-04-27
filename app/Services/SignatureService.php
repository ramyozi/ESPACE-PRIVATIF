<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentState;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\SignatureRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Coordonne l'enchainement de la signature :
 *  1. start  -> verifie l'etat, declenche l'OTP
 *  2. complete -> valide l'OTP, persiste la signature, transitionne le document
 *  3. SothisGateway prend ensuite le relai (push WebSocket)
 *
 * La validation et le stockage du fichier PNG sont delegues au
 * SignatureFileService pour respecter le SRP.
 */
final class SignatureService
{
    public function __construct(
        private readonly SignatureRepository $signatures,
        private readonly DocumentRepository $documents,
        private readonly DocumentService $documentService,
        private readonly OtpService $otpService,
        private readonly MailService $mailService,
        private readonly SothisGateway $sothisGateway,
        private readonly AuditService $audit,
        private readonly SignatureFileService $signatureFiles,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Demarre la signature : passe le document en "signature_en_cours" et envoie l'OTP.
     */
    public function start(User $user, Document $document, ?string $ip = null): void
    {
        if ($this->signatures->existsForDocument($document->id)) {
            throw new RuntimeException('already_signed');
        }

        $this->documentService->startSignature($document, $ip);

        $this->otpService->generateAndSend(
            tenantId: $user->tenantId,
            userId: $user->id,
            userEmail: $user->email,
            target: 'signature_doc_' . $document->id,
        );
    }

    /**
     * Termine la signature : valide l'OTP, valide et stocke l'image,
     * persiste, log l'audit, notifie SOTHIS et envoie les mails.
     *
     * @param string $signatureBase64 Image PNG en data URL strict (data:image/png;base64,...)
     */
    public function complete(
        User $user,
        Document $document,
        string $otp,
        string $signatureBase64,
        ?string $ip,
        ?string $userAgent,
        string $managerEmail = '',
    ): array {
        if ($document->state !== DocumentState::SIGNATURE_EN_COURS) {
            throw new RuntimeException('invalid_state');
        }

        // Verification de l'OTP (avec raccourci dev "123456" si APP_ENV=dev)
        $isDev = ($_ENV['APP_ENV'] ?? 'prod') === 'dev';
        if ($isDev && $otp === '123456') {
            $this->logger->warning('signature.dev_otp_shortcut_used', [
                'document_id' => $document->id,
                'user_id' => $user->id,
            ]);
            $check = ['ok' => true];
        } else {
            $check = $this->otpService->verify(
                $user->id,
                'signature_doc_' . $document->id,
                $otp,
            );
        }
        if (!$check['ok']) {
            throw new RuntimeException($check['reason'] ?? 'otp_invalid');
        }

        // Validation et stockage du PNG via le service dedie.
        // Toute exception est convertie en code metier explicite remontant au controleur.
        try {
            $binary = $this->signatureFiles->decodeAndValidate($signatureBase64);
        } catch (InvalidArgumentException $e) {
            $this->logger->info('signature.invalid_image', [
                'document_id' => $document->id,
                'reason' => $e->getMessage(),
            ]);
            throw new RuntimeException($e->getMessage());
        }

        $imageSha = hash('sha256', $binary);
        $imagePath = $this->signatureFiles->store($document->id, $binary);

        // Enregistrement DB
        $signedAt = new DateTimeImmutable('now');
        $signatureId = $this->signatures->create([
            'tenant' => $user->tenantId,
            'document' => $document->id,
            'user' => $user->id,
            'field' => null, // simplifie : un seul champ par document pour cette V1
            'path' => $imagePath,
            'sha' => $imageSha,
            'signed' => $signedAt->format('Y-m-d H:i:s.v'),
            'ip' => $ip,
            'ua' => $userAgent ? substr($userAgent, 0, 255) : null,
            'method' => 'otp_email',
            'proof' => json_encode([
                'method' => 'otp_email',
                'otp_validated_at' => $signedAt->format(DATE_ATOM),
            ]),
        ]);

        // Transition document : signature_en_cours -> signe
        $this->documentService->markSigned($document, $ip);

        // Notification SOTHIS via outbox WebSocket
        $this->sothisGateway->queueSignatureCompleted(
            tenantId: $user->tenantId,
            document: $document,
            user: $user,
            signedAt: $signedAt,
            signatureBase64: base64_encode($binary),
            signatureSha: $imageSha,
            ip: $ip,
            userAgent: $userAgent,
        );

        // Mails de confirmation
        $this->mailService->queue(
            tenantId: $user->tenantId,
            to: $user->email,
            subject: 'Confirmation de signature',
            template: 'signature_done_locataire',
            variables: ['document' => $document->title, 'signedAt' => $signedAt->format(DATE_ATOM)],
        );
        if ($managerEmail !== '') {
            $this->mailService->queue(
                tenantId: $user->tenantId,
                to: $managerEmail,
                subject: 'Document signe par un locataire',
                template: 'signature_done_manager',
                variables: [
                    'document' => $document->title,
                    'locataire' => $user->fullName(),
                    'signedAt' => $signedAt->format(DATE_ATOM),
                ],
            );
        }

        $this->logger->info('signature.completed', [
            'document_id' => $document->id,
            'user_id' => $user->id,
            'image_path' => $imagePath,
        ]);

        return [
            'signatureId' => $signatureId,
            'signedAt' => $signedAt->format(DATE_ATOM),
            'state' => DocumentState::SIGNE->value,
            'imagePath' => $imagePath,
        ];
    }

    public function refuse(User $user, Document $document, ?string $reason, ?string $ip): void
    {
        $this->documentService->refuse($document, $reason, $ip);
    }
}
