<?php

declare(strict_types=1);

/**
 * Chargement des variables d'environnement.
 *
 * Local : on lit le fichier .env via vlucas/phpdotenv (safeLoad : pas d'erreur
 * si fichier absent).
 *
 * Cloud (Render, etc.) : pas de fichier .env, les variables sont injectees
 * dans l'environnement systeme. Or PHP 8 a `variables_order=GPCS` par defaut
 * (sans 'E'), donc $_ENV peut etre vide alors que getenv() retourne les
 * bonnes valeurs. On hydrate $_ENV a partir de getenv() pour garder un acces
 * uniforme via $_ENV[...] dans tout le code.
 *
 * Ce fichier est inclus par public/index.php et tous les scripts CLI
 * (bin/seed-users.php, bin/migrate-postgres.php, bin/ws-server.php, etc.).
 */

$root = dirname(__DIR__);

if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

// Hydrate $_ENV depuis getenv() pour les variables manquantes (cas cloud).
$systemEnv = getenv();
if (is_array($systemEnv)) {
    foreach ($systemEnv as $key => $value) {
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}
