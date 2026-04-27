<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentState;
use App\Models\User;
use App\Repositories\DocumentRepository;
use App\Repositories\SignatureRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Coordonne l'enchainement de la signature :
 *  1. start  -> verifie l'etat, declenche l'OTP
 *  2. complete -> valide l'OTP, persiste la signature, transitionne le document
 *  3. SothisGateway prend ensuite le relai (push WebSocket)
 */
final class SignatureService
{
    private const SIGNATURE_DIR = __DIR__ . '/../../storage/signatures';

    public function __construct(
        private readonly SignatureRepository $signatures,
        private readonly DocumentRepository $documents,
        private readonly DocumentService $documentService,
        private readonly OtpService $otpService,
        private readonly MailService $mailService,
        private readonly SothisGateway $sothisGateway,
        private readonly AuditService $audit,
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
     * Termine la signature : valide l'OTP, enregistre l'image, log l'audit,
     * notifie SOTHIS et envoie les mails.
     *
     * @param string $signatureBase64 Image PNG en base64 (data URL ou nu)
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

        // On détecte si on est en environnement de développement
        $isDev = ($_ENV['APP_ENV'] ?? 'prod') === 'dev';

        // En mode dev, on accepte un OTP fixe pour faciliter les tests
        if ($isDev && $otp === '123456') {
            // OTP valide automatiquement en mode développement.
            // On trace systematiquement l'utilisation de ce raccourci :
            // si on le voit apparaitre en production, c'est qu'APP_ENV=dev a fuité.
            $this->logger->warning('signature.dev_otp_shortcut_used', [
                'document_id' => $document->id,
                'user_id' => $user->id,
            ]);
            $check = ['ok' => true];
        } else {
            // En production (ou si OTP différent), on vérifie via le service OTP réel
            $check = $this->otpService->verify(
                $user->id,
                'signature_doc_' . $document->id,
                $otp,
            );
        }

        // Si l'OTP est invalide, on bloque la signature
        if (!$check['ok']) {
            throw new RuntimeException($check['reason'] ?? 'otp_invalid');
        }

        // On extrait le PNG depuis le data URL eventuel
        $binary = $this->decodeSignaturePng($signatureBase64);
        if ($binary === null) {
            throw new RuntimeException('invalid_image');
        }

        $imageSha = hash('sha256', $binary);
        $imagePath = $this->persistSignatureFile($user->tenantId, $document->id, $binary);

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

        // Mails
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
        ]);

        return [
            'signatureId' => $signatureId,
            'signedAt' => $signedAt->format(DATE_ATOM),
            'state' => DocumentState::SIGNE->value,
        ];
    }

    public function refuse(User $user, Document $document, ?string $reason, ?string $ip): void
    {
        $this->documentService->refuse($document, $reason, $ip);
    }

    /**
     * Decode une image PNG depuis un data URL ou une string base64 nue.
     */
    private function decodeSignaturePng(string $input): ?string
    {
        if (preg_match('/^data:image\/png;base64,(.+)$/i', $input, $m)) {
            $input = $m[1];
        }
        $binary = base64_decode($input, true);
        if ($binary === false) {
            return null;
        }
        // Verifie la signature PNG (8 octets magiques)
        if (substr($binary, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }
        if (strlen($binary) > 200_000) {
            // Limite raisonnable, le reste est trop gros pour une signature canvas
            return null;
        }
        return $binary;
    }

    private function persistSignatureFile(int $tenantId, int $documentId, string $binary): string
    {
        $dir = self::SIGNATURE_DIR . '/' . $tenantId;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = sprintf('doc-%d-%s.png', $documentId, bin2hex(random_bytes(6)));
        $full = $dir . '/' . $name;
        file_put_contents($full, $binary);
        // On stocke un chemin relatif au repo, jamais expose tel quel
        return 'storage/signatures/' . $tenantId . '/' . $name;
    }
}
