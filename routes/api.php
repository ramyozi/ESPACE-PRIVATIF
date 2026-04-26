<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DocumentController;
use App\Controllers\HealthController;
use App\Controllers\SignatureController;
use App\Controllers\SothisController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Slim\App;

return function (App $app): void {
    // Sante du service
    $app->get('/health', [HealthController::class, 'check']);

    // Authentification du locataire
    // - /login : pas de CSRF (premier appel, pas encore de session)
    // - /logout : CSRF requis
    // - /me, /csrf-token : GET, sans effet de bord, donc sans CSRF
    $app->post('/api/auth/login', [AuthController::class, 'login']);
    $app->post('/api/auth/logout', [AuthController::class, 'logout'])->add(CsrfMiddleware::class);
    $app->get('/api/auth/me', [AuthController::class, 'me'])->add(AuthMiddleware::class);
    $app->get('/api/auth/csrf-token', [AuthController::class, 'csrfToken']);

    // Documents : lecture sans CSRF (GET), routes mutantes protegees par CsrfMiddleware
    $app->group('/api/documents', function ($group): void {
        $group->get('', [DocumentController::class, 'list']);
        $group->get('/{id:[0-9]+}', [DocumentController::class, 'show']);
        $group->post('/{id:[0-9]+}/sign/start', [SignatureController::class, 'start'])
            ->add(CsrfMiddleware::class);
        $group->post('/{id:[0-9]+}/sign/complete', [SignatureController::class, 'complete'])
            ->add(CsrfMiddleware::class);
        $group->post('/{id:[0-9]+}/refuse', [SignatureController::class, 'refuse'])
            ->add(CsrfMiddleware::class);
    })->add(AuthMiddleware::class);

    // Endpoint serveur a serveur SOTHIS : auth par cle API, pas de CSRF
    $app->post('/api/sothis/document/finalized', [SothisController::class, 'finalized']);
};
