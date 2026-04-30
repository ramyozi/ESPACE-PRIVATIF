<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Token d'acces court signe (HMAC SHA-256) qui autorise le telechargement
 * d'un PDF cible. Sert a contourner les restrictions de cookie cross-origin
 * (iframe, ouverture target="_blank", 3rd-party cookies bloques).
 *
 * Le token est genere apres verification de la session (route protegee), puis
 * peut etre passe en query string sur /api/documents/{id}/pdf?token=...
 * pendant sa duree de vie tres courte (60 secondes par defaut).
 *
 * Format : base64url(payload).base64url(hmac)
 *  - payload : "u={userId};d={docId};e={expTimestamp}"
 *  - hmac    : HMAC SHA-256 du payload avec APP_SECRET
 *
 * Pas de stockage BDD : tout est dans le token (stateless), validable cote
 * serveur sans round-trip.
 */
final class PdfAccessTokenService
{
    /** Duree de vie : 60 secondes, juste assez pour charger l'iframe et lancer un download. */
    private const TTL_SECONDS = 60;

    public function __construct(private readonly string $secret)
    {
    }

    public function issue(int $userId, int $documentId): string
    {
        $exp = time() + self::TTL_SECONDS;
        $payload = sprintf('u=%d;d=%d;e=%d', $userId, $documentId, $exp);
        $sig = hash_hmac('sha256', $payload, $this->secret, true);
        return self::base64url($payload) . '.' . self::base64url($sig);
    }

    /**
     * Valide le token et retourne ['userId' => int, 'documentId' => int]
     * ou null si invalide / expire / signature fausse.
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;

        $payload = self::base64urlDecode($parts[0]);
        $sig = self::base64urlDecode($parts[1]);
        if ($payload === null || $sig === null) return null;

        $expected = hash_hmac('sha256', $payload, $this->secret, true);
        if (!hash_equals($expected, $sig)) return null;

        // Parse "u=..;d=..;e=.."
        $data = [];
        foreach (explode(';', $payload) as $kv) {
            [$k, $v] = array_pad(explode('=', $kv, 2), 2, null);
            if ($k !== null && $v !== null) $data[$k] = $v;
        }
        if (!isset($data['u'], $data['d'], $data['e'])) return null;

        $exp = (int) $data['e'];
        if ($exp < time()) return null;

        return [
            'userId' => (int) $data['u'],
            'documentId' => (int) $data['d'],
        ];
    }

    private static function base64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $s): ?string
    {
        $b64 = strtr($s, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) $b64 .= str_repeat('=', 4 - $pad);
        $decoded = base64_decode($b64, true);
        return $decoded === false ? null : $decoded;
    }
}
