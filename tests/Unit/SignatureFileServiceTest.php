<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SignatureFileService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests du service de validation et stockage des images de signature.
 *
 * On utilise un PNG minimal valide 1x1 pour simuler une signature legitime,
 * puis on teste les chemins d'erreur (format, encodage, taille).
 */
final class SignatureFileServiceTest extends TestCase
{
    /**
     * PNG minimal 1x1 transparent. Suffit pour passer la verification
     * du data URL et des octets magiques.
     */
    private const VALID_PNG_DATA_URL = 'data:image/png;base64,'
        . 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlE'
        . 'QVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==';

    /** @var string[] Fichiers crees pendant les tests, a nettoyer en tearDown. */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function testDecodeAndValidateAccepteUnPngValide(): void
    {
        $service = new SignatureFileService();
        $binary = $service->decodeAndValidate(self::VALID_PNG_DATA_URL);

        // Octets magiques d'un PNG : 89 50 4E 47 0D 0A 1A 0A
        self::assertSame("\x89PNG\r\n\x1a\n", substr($binary, 0, 8));
    }

    public function testDecodeAndValidateRefuseSiPasDeDataUrl(): void
    {
        $service = new SignatureFileService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid_image_format');

        // Base64 nu, sans le prefixe "data:image/png;base64,"
        $service->decodeAndValidate('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJ');
    }

    public function testDecodeAndValidateRefuseUnAutreTypeMime(): void
    {
        $service = new SignatureFileService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid_image_format');

        // Mime jpeg : on attend strictement png
        $service->decodeAndValidate('data:image/jpeg;base64,/9j/4AAQSkZJRg==');
    }

    public function testDecodeAndValidateRefuseUnFauxPng(): void
    {
        $service = new SignatureFileService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid_image_signature');

        // Base64 valide mais qui ne contient pas les octets magiques PNG
        $fakeBase64 = base64_encode('NOT-A-PNG-FILE-CONTENT-XX');
        $service->decodeAndValidate('data:image/png;base64,' . $fakeBase64);
    }

    public function testDecodeAndValidateRefuseUneImageTropGrosse(): void
    {
        $service = new SignatureFileService();

        // On fabrique un binaire qui depasse 1 Mo en commencant par les octets PNG
        // afin de passer la verification de signature mais pas celle de taille.
        $oversized = "\x89PNG\r\n\x1a\n" . str_repeat('A', SignatureFileService::MAX_SIZE_BYTES + 1);
        $dataUrl = 'data:image/png;base64,' . base64_encode($oversized);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('image_too_large');

        $service->decodeAndValidate($dataUrl);
    }

    public function testStoreCreeUnFichierAvecLeNomAttendu(): void
    {
        $service = new SignatureFileService();
        $binary = $service->decodeAndValidate(self::VALID_PNG_DATA_URL);

        $relativePath = $service->store(documentId: 42, binary: $binary);
        $this->createdFiles[] = __DIR__ . '/../../' . $relativePath;

        // Format attendu : signature_42_{timestamp}.png
        self::assertMatchesRegularExpression(
            '#^storage/signatures/signature_42_\d+\.png$#',
            $relativePath,
        );

        $absolutePath = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($absolutePath);
        // Et le fichier ecrit doit bien commencer par les octets PNG
        self::assertSame("\x89PNG\r\n\x1a\n", substr((string) file_get_contents($absolutePath), 0, 8));
    }

    public function testValidationCompletePuisStockage(): void
    {
        // Test bout en bout : valid -> binary -> stored on disk
        $service = new SignatureFileService();

        $binary = $service->decodeAndValidate(self::VALID_PNG_DATA_URL);
        $path = $service->store(7, $binary);
        $this->createdFiles[] = __DIR__ . '/../../' . $path;

        self::assertFileExists(__DIR__ . '/../../' . $path);
        self::assertGreaterThan(0, filesize(__DIR__ . '/../../' . $path));
    }
}
