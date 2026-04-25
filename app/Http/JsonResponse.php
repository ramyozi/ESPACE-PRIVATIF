<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Helpers pour produire des reponses JSON normalisees.
 * Format : { status: "ok|error", data?: ..., error?: { code, message } }
 */
final class JsonResponse
{
    public static function ok(ResponseInterface $response, mixed $data = null, int $status = 200): ResponseInterface
    {
        return self::write($response, ['status' => 'ok', 'data' => $data], $status);
    }

    public static function error(
        ResponseInterface $response,
        string $code,
        string $message,
        int $status = 400,
    ): ResponseInterface {
        return self::write(
            $response,
            ['status' => 'error', 'error' => ['code' => $code, 'message' => $message]],
            $status,
        );
    }

    private static function write(ResponseInterface $response, array $payload, int $status): ResponseInterface
    {
        $response->getBody()->write(
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
