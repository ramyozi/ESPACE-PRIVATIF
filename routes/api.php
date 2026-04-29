<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\DocumentController;
use App\Controllers\HealthController;
use App\Controllers\SignatureController;
use App\Controllers\SothisController;
use App\Middleware\AdminMiddleware;
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
    // Mise a jour profil (email + mot de passe) : auth + CSRF requis
    $app->post('/api/auth/profile', [AuthController::class, 'updateProfile'])
        ->add(CsrfMiddleware::class)
        ->add(AuthMiddleware::class);

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

    // Endpoints serveur a serveur SOTHIS : auth par cle API, pas de CSRF
    $app->post('/api/sothis/document/finalized', [SothisController::class, 'finalized']);
    $app->post('/api/sothis/documents', [SothisController::class, 'deposit']);

    // Espace admin : auth + role admin (CSRF sur les routes mutantes uniquement)
    $app->get('/api/admin/users', [AdminController::class, 'listUsers'])
        ->add(AdminMiddleware::class)
        ->add(AuthMiddleware::class);

    $app->post('/api/admin/documents', [AdminController::class, 'createDocument'])
        ->add(CsrfMiddleware::class)
        ->add(AdminMiddleware::class)
        ->add(AuthMiddleware::class);

    // Upload PDF + creation document en une etape (multipart/form-data)
    $app->post('/api/admin/documents/upload', [AdminController::class, 'uploadDocument'])
        ->add(CsrfMiddleware::class)
        ->add(AdminMiddleware::class)
        ->add(AuthMiddleware::class);
};
