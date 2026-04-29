<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Stockage local des PDF deposes par l'admin.
 *
 * Convention de nommage : doc_{tenantId}_{timestamp}_{random}.pdf
 * Repertoire : storage/pdfs/{tenantId}/
 *
 * Le chemin retourne est relatif au projet (jamais expose tel quel).
 * En production cloud, ce service pourra etre remplace par un adapter S3
 * sans toucher au reste de l'app (interface deja minimale).
 */
final class PdfStorageService
{
    /** Taille maximum d'un PDF accepte : 10 Mo. */
    public const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    /** Magic bytes d'un PDF valide : "%PDF-". */
    private const PDF_MAGIC = "%PDF-";

    private const STORAGE_DIR = __DIR__ . '/../../storage/pdfs';

    /**
     * Stocke un fichier PDF uploade et retourne le chemin relatif au projet.
     *
     * @throws InvalidArgumentException avec un code metier explicite :
     *   - upload_error           : erreur HTTP (taille, transmission)
     *   - invalid_pdf_format     : magic bytes absents
     *   - pdf_too_large          : depasse MAX_SIZE_BYTES
     * @throws RuntimeException si l'ecriture sur disque echoue.
     */
    public function store(UploadedFileInterface $file, int $tenantId): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('upload_error');
        }

        if ($file->getSize() !== null && $file->getSize() > self::MAX_SIZE_BYTES) {
            throw new InvalidArgumentException('pdf_too_large');
        }

        $stream = $file->getStream();
        $stream->rewind();
        $head = $stream->read(8);
        if (!str_starts_with($head, self::PDF_MAGIC)) {
            throw new InvalidArgumentException('invalid_pdf_format');
        }

        $dir = self::STORAGE_DIR . '/' . $tenantId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('storage_unavailable');
        }

        $filename = sprintf('doc_%d_%d_%s.pdf', $tenantId, time(), bin2hex(random_bytes(4)));
        $fullPath = $dir . '/' . $filename;

        $file->moveTo($fullPath);
        if (!is_file($fullPath)) {
            throw new RuntimeException('storage_write_failed');
        }

        return 'storage/pdfs/' . $tenantId . '/' . $filename;
    }
}
