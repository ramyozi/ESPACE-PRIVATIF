<?php

declare(strict_types=1);

/**
 * Joue les fichiers SQL de migrations/postgres/ sur la base configuree dans .env
 * (DB_DRIVER=pgsql). Les fichiers sont executes dans l'ordre lexicographique
 * (001_*.sql, 002_*.sql, 003_*.sql, ...).
 *
 * Usage local :
 *   php bin/migrate-postgres.php
 *
 * Usage Render (one-off job) :
 *   docker-compose run --rm app php bin/migrate-postgres.php
 *
 * Le script est idempotent : tous les CREATE / INSERT utilisent IF NOT EXISTS
 * ou ON CONFLICT, donc on peut le rejouer sans risque.
 *
 * Apres ce script, lancer bin/seed-users.php pour creer les utilisateurs
 * avec des hash bcrypt corrects.
 */

use App\Database\Connection;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap-env.php';

$dbConfig = [
    'driver' => $_ENV['DB_DRIVER'] ?? 'pgsql',
    'host' => $_ENV['DB_HOST'] ?? '',
    'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
    'name' => $_ENV['DB_NAME'] ?? 'postgres',
    'user' => $_ENV['DB_USER'] ?? '',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
];

if (($dbConfig['driver'] ?? '') !== 'pgsql') {
    fwrite(STDERR, "Erreur : DB_DRIVER doit valoir 'pgsql' (actuel : {$dbConfig['driver']}).\n");
    exit(1);
}

$pdo = (new Connection($dbConfig))->pdo();
echo "Connecte a {$dbConfig['host']}:{$dbConfig['port']}/{$dbConfig['name']}\n\n";

$dir = __DIR__ . '/../migrations/postgres';
$files = glob($dir . '/*.sql');
sort($files, SORT_STRING);

if ($files === [] || $files === false) {
    fwrite(STDERR, "Aucun fichier SQL trouve dans {$dir}\n");
    exit(1);
}

foreach ($files as $file) {
    $name = basename($file);
    echo "-> {$name}";
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "  [erreur de lecture]\n";
        continue;
    }
    try {
        // PDO PostgreSQL accepte plusieurs instructions par exec().
        $pdo->exec($sql);
        echo "  ok\n";
    } catch (\PDOException $e) {
        echo "  ECHEC : " . $e->getMessage() . "\n";
        exit(2);
    }
}

echo "\nMigrations PostgreSQL terminees. Lancer ensuite : php bin/seed-users.php\n";
