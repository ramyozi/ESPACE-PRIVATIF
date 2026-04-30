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

    // Mot de passe oublie : ces deux endpoints sont publics (pas de session,
    // pas de CSRF puisqu'on n'a justement pas encore de session pour generer
    // un token). La protection repose sur le token aleatoire envoye par mail.
    $app->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    $app->post('/api/auth/reset-password', [AuthController::class, 'resetPassword']);
    // Mise a jour profil (email + mot de passe) : auth + CSRF requis
    $app->post('/api/auth/profile', [AuthController::class, 'updateProfile'])
        ->add(CsrfMiddleware::class)
        ->add(AuthMiddleware::class);

    // Documents : lecture sans CSRF (GET), routes mutantes protegees par CsrfMiddleware.
    // Le PDF est sorti du groupe pour pouvoir accepter une auth alternative
    // par token URL (cf. plus bas) sans cookie.
    $app->group('/api/documents', function ($group): void {
        $group->get('', [DocumentController::class, 'list']);
        $group->get('/{id:[0-9]+}', [DocumentController::class, 'show']);
        // Emission d'un token court (60s) pour acceder au PDF sans cookie.
        $group->get('/{id:[0-9]+}/pdf-token', [DocumentController::class, 'pdfToken']);
        $group->post('/{id:[0-9]+}/sign/start', [SignatureController::class, 'start'])
            ->add(CsrfMiddleware::class);
        $group->post('/{id:[0-9]+}/sign/complete', [SignatureController::class, 'complete'])
            ->add(CsrfMiddleware::class);
        $group->post('/{id:[0-9]+}/refuse', [SignatureController::class, 'refuse'])
            ->add(CsrfMiddleware::class);
    })->add(AuthMiddleware::class);

    // Streaming du PDF : auth via token URL (preferentiel) OU session.
    // Pas d'AuthMiddleware ici : la verification est faite dans le controleur
    // pour permettre les acces iframe / target=_blank cross-origin sans cookie.
    $app->get(
        '/api/documents/{id:[0-9]+}/pdf',
        [DocumentController::class, 'downloadPdf'],
    );

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
