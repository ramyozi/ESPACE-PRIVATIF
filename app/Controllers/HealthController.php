<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Endpoint de sante du service.
 * Verifie que la base est joignable, sert au healthcheck Docker.
 */
final class HealthController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function check(Request $request, Response $response): Response
    {
        $dbStatus = 'ok';
        try {
            $this->connection->pdo()->query('SELECT 1');
        } catch (Throwable $e) {
            $dbStatus = 'down';
        }

        $payload = [
            'status' => $dbStatus === 'ok' ? 'ok' : 'degraded',
            'service' => 'espace-privatif',
            'time' => date(DATE_ATOM),
            'checks' => [
                'database' => $dbStatus,
            ],
        ];

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($dbStatus === 'ok' ? 200 : 503);
    }
}
