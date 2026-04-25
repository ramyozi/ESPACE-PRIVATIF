<?php

declare(strict_types=1);

/**
 * Script de seed des locataires de demo + documents associes.
 *
 * Usage :
 *   docker-compose exec app php bin/seed-users.php
 *
 * Cree alice@example.test et bob@example.test avec le mot de passe "demo1234".
 */

use App\Database\Connection;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$dbConfig = [
    'host' => $_ENV['DB_HOST'] ?? 'db',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'name' => $_ENV['DB_NAME'] ?? 'espace_privatif',
    'user' => $_ENV['DB_USER'] ?? 'app',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
];

$pdo = (new Connection($dbConfig))->pdo();

// Mot de passe commun aux deux comptes de demo
$password = 'demo1234';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$users = [
    ['external_id' => 'LOC-1001', 'email' => 'alice@example.test', 'first' => 'Alice', 'last' => 'Martin', 'phone' => '+33600000001'],
    ['external_id' => 'LOC-1002', 'email' => 'bob@example.test',   'first' => 'Bob',   'last' => 'Durand', 'phone' => '+33600000002'],
];

$stmt = $pdo->prepare(
    'INSERT INTO users (tenant_id, residence_id, external_id, email, password_hash, first_name, last_name, phone)
     VALUES (1, 1, :ext, :email, :hash, :first, :last, :phone)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
);

foreach ($users as $u) {
    $stmt->execute([
        'ext' => $u['external_id'],
        'email' => $u['email'],
        'hash' => $hash,
        'first' => $u['first'],
        'last' => $u['last'],
        'phone' => $u['phone'],
    ]);
    echo "Locataire ok : {$u['email']}\n";
}

// Recuperation des ids reels (au cas ou ils auraient deja existe)
$ids = $pdo->query(
    "SELECT id, external_id FROM users WHERE external_id IN ('LOC-1001','LOC-1002')"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$idByExt = array_flip($ids);

// Documents de demo associes (un par locataire)
$docs = [
    [
        'sothis_id' => 'DOC-2026-0001',
        'user_ext' => 'LOC-1001',
        'type' => 'bail',
        'title' => 'Bail residence Lilas - Alice',
        'pdf' => '/storage/pdfs/demo-bail-alice.pdf',
        'sha' => str_repeat('a', 64),
    ],
    [
        'sothis_id' => 'DOC-2026-0002',
        'user_ext' => 'LOC-1002',
        'type' => 'avenant',
        'title' => 'Avenant loyer 2026 - Bob',
        'pdf' => '/storage/pdfs/demo-avenant-bob.pdf',
        'sha' => str_repeat('b', 64),
    ],
];

$insertDoc = $pdo->prepare(
    'INSERT INTO documents (tenant_id, user_id, residence_id, sothis_document_id, type, title, state, pdf_path, pdf_sha256, deadline)
     VALUES (1, :uid, 1, :sothis, :type, :title, :state, :pdf, :sha, DATE_ADD(NOW(), INTERVAL 14 DAY))
     ON DUPLICATE KEY UPDATE title = VALUES(title)'
);

foreach ($docs as $d) {
    $insertDoc->execute([
        'uid' => $idByExt[$d['user_ext']],
        'sothis' => $d['sothis_id'],
        'type' => $d['type'],
        'title' => $d['title'],
        'state' => 'en_attente_signature',
        'pdf' => $d['pdf'],
        'sha' => $d['sha'],
    ]);
    echo "Document ok : {$d['sothis_id']}\n";
}

echo "\nSeed termine. Mot de passe demo : {$password}\n";
