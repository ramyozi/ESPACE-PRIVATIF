<?php

declare(strict_types=1);

use App\Middleware\JsonBodyParserMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use Slim\App;

return function (App $app): void {
    // Demarre la session HTTP avant tout
    $app->add(SessionMiddleware::class);

    // Parse les corps JSON entrants en tableaux PHP
    $app->add(JsonBodyParserMiddleware::class);

    // Headers de securite communs
    $app->add(SecurityHeadersMiddleware::class);

    // Routing standard Slim (doit etre ajoute en dernier pour s'executer en premier)
    $app->addRoutingMiddleware();
};
