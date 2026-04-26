<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DocumentController;
use App\Controllers\HealthController;
use App\Controllers\SignatureController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Sante du service
    $app->get('/health', [HealthController::class, 'check']);

    // Authentification du locataire
    $app->post('/api/auth/login', [AuthController::class, 'login']);
    $app->post('/api/auth/logout', [AuthController::class, 'logout']);
    $app->get('/api/auth/me', [AuthController::class, 'me'])->add(AuthMiddleware::class);

    // Documents et signature (acces filtre par tenant + user via le middleware)
    $app->group('/api/documents', function ($group): void {
        $group->get('', [DocumentController::class, 'list']);
        $group->get('/{id:[0-9]+}', [DocumentController::class, 'show']);
        $group->post('/{id:[0-9]+}/sign/start', [SignatureController::class, 'start']);
        $group->post('/{id:[0-9]+}/sign/complete', [SignatureController::class, 'complete']);
        $group->post('/{id:[0-9]+}/refuse', [SignatureController::class, 'refuse']);
    })->add(AuthMiddleware::class);
};
