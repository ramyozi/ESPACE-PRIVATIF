# Modélisation base de données

Moteur : MySQL 8, InnoDB, charset utf8mb4, collation utf8mb4_unicode_ci.

Toutes les tables métier portent une colonne tenant_id (FK vers tenants). Les requêtes passent par un repository qui force ce filtre.

## 1. Tables

### 1.1 tenants

Représente un client SOTHIS (une agence, une foncière).

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK auto |
| code | VARCHAR(32) | UNIQUE NOT NULL |
| name | VARCHAR(150) | NOT NULL |
| sothis_api_key_hash | CHAR(64) | NOT NULL (clé partagée hashée) |
| created_at | DATETIME | NOT NULL |
| active | TINYINT(1) | NOT NULL DEFAULT 1 |

### 1.2 residences

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK |
| tenant_id | BIGINT UNSIGNED | FK tenants(id) |
| name | VARCHAR(150) | NOT NULL |
| manager_email | VARCHAR(190) | NOT NULL |
| created_at | DATETIME | NOT NULL |

Index : (tenant_id).

### 1.3 users (locataires)

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK |
| tenant_id | BIGINT UNSIGNED | FK tenants(id) |
| residence_id | BIGINT UNSIGNED | FK residences(id) |
| external_id | VARCHAR(64) | identifiant SOTHIS |
| email | VARCHAR(190) | NOT NULL |
| password_hash | VARCHAR(255) | NULL (autorise lien magique seul) |
| first_name | VARCHAR(100) | |
| last_name | VARCHAR(100) | |
| phone | VARCHAR(30) | |
| failed_logins | SMALLINT | DEFAULT 0 |
| locked_until | DATETIME | NULL |
| created_at | DATETIME | NOT NULL |

Index : UNIQUE (tenant_id, email), UNIQUE (tenant_id, external_id).

### 1.4 documents

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK |
| tenant_id | BIGINT UNSIGNED | FK |
| user_id | BIGINT UNSIGNED | FK users(id) |
| residence_id | BIGINT UNSIGNED | FK residences(id) |
| sothis_document_id | VARCHAR(64) | identifiant SOTHIS |
| type | VARCHAR(40) | bail, avenant, etat_lieux, etc. |
| title | VARCHAR(200) | NOT NULL |
| state | ENUM | en_attente_signature, signature_en_cours, signe, signe_valide, refuse, expire |
| pdf_path | VARCHAR(500) | chemin local ou URL |
| pdf_sha256 | CHAR(64) | empreinte du PDF d'origine |
| signed_pdf_path | VARCHAR(500) | NULL, rempli après retour SOTHIS |
| deadline | DATETIME | NULL |
| created_at | DATETIME | NOT NULL |
| updated_at | DATETIME | NOT NULL |

Index : (tenant_id, state), (tenant_id, user_id), UNIQUE (tenant_id, sothis_document_id).

### 1.5 signature_fields

Champs de signature attendus pour un document (positionnement).

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK |
| document_id | BIGINT UNSIGNED | FK documents(id) ON DELETE CASCADE |
| name | VARCHAR(60) | NOT NULL |
| page | SMALLINT | NOT NULL |
| pos_x | INT | NOT NULL |
| pos_y | INT | NOT NULL |
| width | INT | NOT NULL |
| height | INT | NOT NULL |
| required | TINYINT(1) | DEFAULT 1 |

### 1.6 signatures

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK |
| tenant_id | BIGINT UNSIGNED | FK |
| document_id | BIGINT UNSIGNED | FK documents(id) |
| user_id | BIGINT UNSIGNED | FK users(id) |
| signature_field_id | BIGINT UNSIGNED | FK signature_fields(id) |
| image_path | VARCHAR(500) | PNG stocké hors webroot |
| image_sha256 | CHAR(64) | empreinte du PNG |
| signed_at | DATETIME(3) | précision ms |
| ip | VARCHAR(45) | IPv4/IPv6 |
| user_agent | VARCHAR(255) | |
| consent_method | ENUM('otp_email','otp_sms') | |
| consent_proof | JSON | code envoyé hashé, horodatage |

Index : (tenant_id, document_id).

### 1.7 magic_links

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK |
| tenant_id | BIGINT UNSIGNED | FK |
| user_id | BIGINT UNSIGNED | FK |
| token_hash | CHAR(64) | UNIQUE |
| purpose | ENUM('login','signature') | |
| document_id | BIGINT UNSIGNED | NULL |
| expires_at | DATETIME | NOT NULL |
| consumed_at | DATETIME | NULL |
| created_at | DATETIME | NOT NULL |

### 1.8 otp_codes

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK |
| tenant_id | BIGINT UNSIGNED | FK |
| user_id | BIGINT UNSIGNED | FK |
| code_hash | CHAR(64) | NOT NULL |
| target | VARCHAR(40) | signature_doc_<id> |
| attempts | TINYINT | DEFAULT 0 |
| expires_at | DATETIME | NOT NULL |
| consumed_at | DATETIME | NULL |

### 1.9 audit_log

Journal append only.

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK |
| tenant_id | BIGINT UNSIGNED | FK |
| user_id | BIGINT UNSIGNED | NULL |
| action | VARCHAR(60) | login, view_doc, sign_start, sign_complete, refuse, etc. |
| target_type | VARCHAR(40) | document, signature, user |
| target_id | VARCHAR(64) | |
| ip | VARCHAR(45) | |
| context | JSON | |
| prev_hash | CHAR(64) | hash de la ligne précédente du tenant |
| row_hash | CHAR(64) | hash de la ligne courante |
| created_at | DATETIME(3) | NOT NULL |

Permissions MySQL : INSERT et SELECT seulement pour le rôle applicatif.

### 1.10 ws_outbox

File de messages WebSocket à émettre vers SOTHIS.

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK |
| tenant_id | BIGINT UNSIGNED | FK |
| message_id | CHAR(36) | UNIQUE |
| type | VARCHAR(60) | |
| payload | JSON | |
| status | ENUM('pending','sent','acked','failed') | |
| attempts | INT | DEFAULT 0 |
| last_error | TEXT | NULL |
| created_at | DATETIME | NOT NULL |
| acked_at | DATETIME | NULL |

### 1.11 mail_queue

| Champ | Type | Contrainte |
|-------|------|------------|
| id | BIGINT UNSIGNED | PK |
| tenant_id | BIGINT UNSIGNED | FK |
| to_email | VARCHAR(190) | |
| subject | VARCHAR(200) | |
| template | VARCHAR(60) | |
| variables | JSON | |
| status | ENUM('pending','sent','failed') | |
| attempts | TINYINT | DEFAULT 0 |
| created_at | DATETIME | NOT NULL |
| sent_at | DATETIME | NULL |

## 2. Relations principales

```
tenants 1---n residences 1---n users 1---n documents 1---n signature_fields
                                                  1---n signatures
documents 1---1 signed_pdf (chemin)
users 1---n magic_links
users 1---n otp_codes
tenants 1---n audit_log
tenants 1---n ws_outbox
tenants 1---n mail_queue
```

## 3. Stratégie multi-tenant

- Une seule base, schéma partagé, isolation par tenant_id.
- Toutes les requêtes passent par la couche Repository qui exige un TenantContext.
- Index composites systématiques (tenant_id, ...) pour préserver les performances.
- Contraintes UNIQUE toujours portées par tenant_id en premier champ.

## 4. Sécurité base

- Comptes MySQL dédiés à l'application avec privilèges minimaux.
- Mots de passe hashés via password_hash (bcrypt cost 12).
- Tokens (magic links, OTP) stockés uniquement sous forme de hash SHA-256.
- Sauvegarde quotidienne chiffrée avec rotation 30 jours.
- Migrations versionnées dans /migrations, jamais d'altération directe.
