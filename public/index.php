<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Chargement des variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Construction du container DI
$containerBuilder = new ContainerBuilder();
(require __DIR__ . '/../config/dependencies.php')($containerBuilder);
$container = $containerBuilder->build();

// Bootstrap Slim
AppFactory::setContainer($container);
$app = AppFactory::create();

// Middlewares globaux
(require __DIR__ . '/../config/middleware.php')($app);

// Routes API
(require __DIR__ . '/../routes/api.php')($app);

// Gestion des erreurs : verbeux en dev, generique en prod
$displayErrors = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);

$app->run();
