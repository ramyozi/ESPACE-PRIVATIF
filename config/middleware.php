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

    // Routing standard Slim (doit etre ajoute en dernier ici pour s'executer
    // en premier vis-a-vis des middlewares ci-dessus).
    //
    // CorsMiddleware est ajoute dans public/index.php APRES l'ErrorMiddleware
    // pour qu'il devienne l'outermost et habille toutes les reponses (y compris
    // les erreurs 4xx/5xx) avec les headers CORS attendus par le frontend.
    $app->addRoutingMiddleware();
};
