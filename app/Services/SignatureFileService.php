<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Service responsable de la validation et du stockage de l'image de signature.
 *
 * Sorti du SignatureService pour respecter le SRP : cette classe ne sait rien
 * des documents, des OTP ou de SOTHIS. Elle valide un data URL PNG, le decode,
 * verifie la taille et persiste le binaire sur disque.
 */
final class SignatureFileService
{
    /** Taille maximale acceptee : 1 Mo. */
    public const MAX_SIZE_BYTES = 1_048_576;

    /**
     * Octets magiques d'un fichier PNG valide. On verifie en plus du data URL
     * pour eviter qu'un attaquant n'envoie un faux PNG mascarade.
     */
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /** Repertoire de stockage relatif a la racine du projet. */
    private const STORAGE_DIR = __DIR__ . '/../../storage/signatures';

    /**
     * Decode et valide une signature recue au format data:image/png;base64,...
     *
     * @return string Binaire PNG (les octets bruts, prets a etre persistes)
     * @throws InvalidArgumentException avec un code metier explicite :
     *   - invalid_image_format       : prefixe data URL absent ou autre type MIME
     *   - invalid_image_encoding     : base64 invalide
     *   - invalid_image_signature    : pas un PNG (octets magiques absents)
     *   - image_too_large            : depasse MAX_SIZE_BYTES
     */
    public function decodeAndValidate(string $dataUrl): string
    {
        // Le format strict est attendu : on n'accepte que les data URL PNG.
        if (!preg_match('/^data:image\/png;base64,(.+)$/i', $dataUrl, $matches)) {
            throw new InvalidArgumentException('invalid_image_format');
        }

        $binary = base64_decode($matches[1], true);
        if ($binary === false) {
            throw new InvalidArgumentException('invalid_image_encoding');
        }

        if (substr($binary, 0, 8) !== self::PNG_SIGNATURE) {
            throw new InvalidArgumentException('invalid_image_signature');
        }

        if (strlen($binary) > self::MAX_SIZE_BYTES) {
            throw new InvalidArgumentException('image_too_large');
        }

        return $binary;
    }

    /**
     * Persiste l'image PNG sur disque et retourne le chemin relatif a la racine
     * du projet (jamais expose tel quel a un client).
     *
     * Convention de nommage : signature_{document_id}_{timestamp}.png
     *
     * @throws RuntimeException si l'ecriture sur disque echoue
     */
    public function store(int $documentId, string $binary): string
    {
        $dir = self::STORAGE_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('storage_unavailable');
        }

        $filename = sprintf('signature_%d_%d.png', $documentId, time());
        $fullPath = $dir . '/' . $filename;

        if (file_put_contents($fullPath, $binary) === false) {
            throw new RuntimeException('storage_write_failed');
        }

        return 'storage/signatures/' . $filename;
    }
}
