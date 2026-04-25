<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Endpoint de sante du service.
 * Sert au healthcheck Docker et permet de tester rapidement que l'app repond.
 */
final class HealthController
{
    public function check(Request $request, Response $response): Response
    {
        $payload = [
            'status' => 'ok',
            'service' => 'espace-privatif',
            'time' => date(DATE_ATOM),
        ];

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
