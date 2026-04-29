<?php

declare(strict_types=1);

/**
 * Script de seed des utilisateurs et documents de demonstration.
 *
 * Usage :
 *   docker-compose exec app php bin/seed-users.php
 *
 * Cree :
 *  - 1 admin (admin@realsoft.fr)
 *  - 2 locataires (alice.martin@example.fr, bruno.lefevre@example.fr)
 *  - 2 documents de demo (un par locataire)
 *
 * Mot de passe commun : "demo1234"
 *
 * Compatible MySQL et PostgreSQL : on detecte le driver via Connection.
 */

use App\Database\Connection;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/bootstrap-env.php';

$dbConfig = [
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'db',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'name' => $_ENV['DB_NAME'] ?? 'espace_privatif',
    'user' => $_ENV['DB_USER'] ?? 'app',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
];

$connection = new Connection($dbConfig);
$pdo = $connection->pdo();
$driver = $connection->driver();

$password = 'demo1234';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$users = [
    [
        'external_id' => 'ADM-0001',
        'email' => 'admin@realsoft.fr',
        'first' => 'Camille',
        'last' => 'Berger',
        'phone' => '+33180000001',
        'role' => 'admin',
    ],
    [
        'external_id' => 'LOC-1001',
        'email' => 'alice.martin@example.fr',
        'first' => 'Alice',
        'last' => 'Martin',
        'phone' => '+33612000001',
        'role' => 'user',
    ],
    [
        'external_id' => 'LOC-1002',
        'email' => 'bruno.lefevre@example.fr',
        'first' => 'Bruno',
        'last' => 'Lefevre',
        'phone' => '+33612000002',
        'role' => 'user',
    ],
];

// Upsert specifique au moteur (PostgreSQL utilise ON CONFLICT, MySQL ON DUPLICATE KEY).
$upsertSql = $driver === 'pgsql'
    ? 'INSERT INTO users (tenant_id, residence_id, external_id, email, password_hash, first_name, last_name, phone, role)
       VALUES (1, 1, :ext, :email, :hash, :first, :last, :phone, :role)
       ON CONFLICT (tenant_id, email) DO UPDATE
         SET password_hash = EXCLUDED.password_hash,
             role = EXCLUDED.role'
    : 'INSERT INTO users (tenant_id, residence_id, external_id, email, password_hash, first_name, last_name, phone, role)
       VALUES (1, 1, :ext, :email, :hash, :first, :last, :phone, :role)
       ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role)';

$stmt = $pdo->prepare($upsertSql);

foreach ($users as $u) {
    $stmt->execute([
        'ext' => $u['external_id'],
        'email' => $u['email'],
        'hash' => $hash,
        'first' => $u['first'],
        'last' => $u['last'],
        'phone' => $u['phone'],
        'role' => $u['role'],
    ]);
    echo str_pad($u['role'], 6) . " ok : {$u['email']}\n";
}

// Recupere les ids des locataires pour creer leurs documents.
$ids = $pdo->query(
    "SELECT id, external_id FROM users WHERE external_id IN ('LOC-1001','LOC-1002')"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$idByExt = array_flip($ids);

$docs = [
    [
        'sothis_id' => 'DOC-2026-0001',
        'user_ext' => 'LOC-1001',
        'type' => 'bail',
        'title' => 'Bail residence Les Lilas - Alice Martin',
        'pdf' => '/demo/Lettre.pdf',
        'sha' => str_repeat('a', 64),
    ],
    [
        'sothis_id' => 'DOC-2026-0002',
        'user_ext' => 'LOC-1002',
        'type' => 'avenant',
        'title' => 'Avenant revision loyer 2026 - Bruno Lefevre',
        'pdf' => '/demo/Lettre.pdf',
        'sha' => str_repeat('b', 64),
    ],
];

// Insert des documents (idempotent via la cle unique tenant_id + sothis_document_id).
$deadlineExpr = $driver === 'pgsql' ? "(NOW() + INTERVAL '14 days')" : 'DATE_ADD(NOW(), INTERVAL 14 DAY)';
$insertSql = $driver === 'pgsql'
    ? "INSERT INTO documents (tenant_id, user_id, residence_id, sothis_document_id, type, title, state, pdf_path, pdf_sha256, deadline)
       VALUES (1, :uid, 1, :sothis, :type, :title, :state, :pdf, :sha, {$deadlineExpr})
       ON CONFLICT (tenant_id, sothis_document_id) DO UPDATE SET title = EXCLUDED.title"
    : "INSERT INTO documents (tenant_id, user_id, residence_id, sothis_document_id, type, title, state, pdf_path, pdf_sha256, deadline)
       VALUES (1, :uid, 1, :sothis, :type, :title, :state, :pdf, :sha, {$deadlineExpr})
       ON DUPLICATE KEY UPDATE title = VALUES(title)";

$insertDoc = $pdo->prepare($insertSql);

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
    echo "doc    ok : {$d['sothis_id']}\n";
}

echo "\nSeed termine. Mot de passe partage : {$password}\n";
echo "Admin : admin@realsoft.fr\n";
echo "Users : alice.martin@example.fr, bruno.lefevre@example.fr\n";
