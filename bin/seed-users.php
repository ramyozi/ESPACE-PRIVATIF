<?php

declare(strict_types=1);

/**
 * Script de seed des utilisateurs et documents de demonstration.
 *
 * Usage :
 *   php bin/seed-users.php
 *
 * Comptes fixes :
 *  - admin@realsoft.fr  (role = admin)
 *  - alex@example.fr    (role = user)
 *
 * Mot de passe commun : "demo1234"
 *
 * IDEMPOTENCE :
 *  - cle unique de seed : (tenant_id, external_id)
 *  - ON CONFLICT met a jour email, password_hash, role, first_name, last_name, phone
 *  - les documents demo utilisent (tenant_id, sothis_document_id) comme cle
 *  - le script peut etre relance N fois sans erreur
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

// Comptes fixes : NE PAS supprimer ni renommer.
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
        'external_id' => 'USR-0001',
        'email' => 'alex@example.fr',
        'first' => 'Alex',
        'last' => 'Dupont',
        'phone' => '+33612000001',
        'role' => 'user',
    ],
];

// UPSERT idempotent sur (tenant_id, external_id) :
//  - PG  : ON CONFLICT (tenant_id, external_id) DO UPDATE
//  - MySQL : ON DUPLICATE KEY UPDATE (la cle unique uq_users_tenant_external joue
//            le meme role)
//
// On met a jour email, password_hash, role, first_name, last_name, phone pour
// que tout changement de ce script soit propage en BDD au prochain run.
$upsertSql = $driver === 'pgsql'
    ? 'INSERT INTO users (tenant_id, residence_id, external_id, email, password_hash, first_name, last_name, phone, role)
       VALUES (1, 1, :ext, :email, :hash, :first, :last, :phone, :role)
       ON CONFLICT (tenant_id, external_id) DO UPDATE
         SET email = EXCLUDED.email,
             password_hash = EXCLUDED.password_hash,
             role = EXCLUDED.role,
             first_name = EXCLUDED.first_name,
             last_name = EXCLUDED.last_name,
             phone = EXCLUDED.phone'
    : 'INSERT INTO users (tenant_id, residence_id, external_id, email, password_hash, first_name, last_name, phone, role)
       VALUES (1, 1, :ext, :email, :hash, :first, :last, :phone, :role)
       ON DUPLICATE KEY UPDATE
             email = VALUES(email),
             password_hash = VALUES(password_hash),
             role = VALUES(role),
             first_name = VALUES(first_name),
             last_name = VALUES(last_name),
             phone = VALUES(phone)';

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

// Recupere l'id du user de demo pour creer son document.
$idStmt = $pdo->prepare(
    "SELECT id FROM users WHERE tenant_id = 1 AND external_id = :ext LIMIT 1"
);
$idStmt->execute(['ext' => 'USR-0001']);
$alexId = $idStmt->fetchColumn();
if ($alexId === false) {
    fwrite(STDERR, "Impossible de retrouver USR-0001 apres seed.\n");
    exit(1);
}

// Document de demo associe a alex@example.fr.
// Cle d'unicite : (tenant_id, sothis_document_id) -> seed reproductible.
$docs = [
    [
        'sothis_id' => 'DOC-2026-0001',
        'user_id' => (int) $alexId,
        'type' => 'contrat',
        'title' => 'Contrat de demonstration',
        'pdf' => 'docs/Lettre.pdf',
        'sha' => str_repeat('a', 64),
    ],
];

$deadlineExpr = $driver === 'pgsql' ? "(NOW() + INTERVAL '14 days')" : 'DATE_ADD(NOW(), INTERVAL 14 DAY)';
$insertSql = $driver === 'pgsql'
    ? "INSERT INTO documents (tenant_id, user_id, residence_id, sothis_document_id, type, title, state, pdf_path, pdf_sha256, deadline)
       VALUES (1, :uid, 1, :sothis, :type, :title, :state, :pdf, :sha, {$deadlineExpr})
       ON CONFLICT (tenant_id, sothis_document_id) DO UPDATE
         SET user_id = EXCLUDED.user_id,
             type = EXCLUDED.type,
             title = EXCLUDED.title,
             pdf_path = EXCLUDED.pdf_path"
    : "INSERT INTO documents (tenant_id, user_id, residence_id, sothis_document_id, type, title, state, pdf_path, pdf_sha256, deadline)
       VALUES (1, :uid, 1, :sothis, :type, :title, :state, :pdf, :sha, {$deadlineExpr})
       ON DUPLICATE KEY UPDATE
             user_id = VALUES(user_id),
             type = VALUES(type),
             title = VALUES(title),
             pdf_path = VALUES(pdf_path)";

$insertDoc = $pdo->prepare($insertSql);

foreach ($docs as $d) {
    $insertDoc->execute([
        'uid' => $d['user_id'],
        'sothis' => $d['sothis_id'],
        'type' => $d['type'],
        'title' => $d['title'],
        'state' => 'en_attente_signature',
        'pdf' => $d['pdf'],
        'sha' => $d['sha'],
    ]);
    echo "doc    ok : {$d['sothis_id']}\n";
}

echo "\nSeed termine (idempotent). Mot de passe partage : {$password}\n";
echo "Admin : admin@realsoft.fr\n";
echo "User  : alex@example.fr\n";
