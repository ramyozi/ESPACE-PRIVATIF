<?php

declare(strict_types=1);

// Buffer toute sortie eventuelle (notice/warning/whitespace) pour eviter
// le "headers already sent" et garantir un body JSON propre.
ob_start();

use App\Middleware\CorsMiddleware;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Chargement uniforme des variables d'environnement (local + cloud)
require __DIR__ . '/../config/bootstrap-env.php';

// Construction du container DI
$containerBuilder = new ContainerBuilder();
(require __DIR__ . '/../config/dependencies.php')($containerBuilder);
$container = $containerBuilder->build();

// Bootstrap Slim
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addBodyParsingMiddleware();

// Middlewares globaux (session, json body, security headers, routing).
// CorsMiddleware n'est volontairement pas ici : il est ajoute APRES
// l'ErrorMiddleware ci-dessous afin de wrapper aussi les reponses d'erreur,
// sinon les exceptions retournent du HTML sans header CORS et le navigateur
// bloque la requete cross-origin.
(require __DIR__ . '/../config/middleware.php')($app);

// Routes API
(require __DIR__ . '/../routes/api.php')($app);

// Gestion des erreurs : verbeux en dev, generique en prod.
// On force le content-type JSON pour toutes les erreurs API.
$displayErrors = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);
$errorMiddleware->getDefaultErrorHandler()->forceContentType('application/json');

// CorsMiddleware ajoute en dernier => devient l'outermost et habille
// AUSSI les reponses produites par l'ErrorMiddleware avec les headers CORS.
$app->add(CorsMiddleware::class);

// Vide tout output eventuel avant d'envoyer la reponse (filet de securite).
if (ob_get_length() !== false && ob_get_length() > 0) {
    ob_clean();
}

$app->run();
