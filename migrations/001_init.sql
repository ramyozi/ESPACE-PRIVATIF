-- ============================================================
-- Schema initial ESPACE-PRIVATIF
-- Cible : MySQL 8, InnoDB, utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- Tenants : un client SOTHIS = un tenant
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    sothis_api_key_hash CHAR(64) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Residences : regroupement logique des locataires
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS residences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    manager_email VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_residences_tenant (tenant_id),
    CONSTRAINT fk_residences_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Users : locataires de l'espace privatif
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    residence_id BIGINT UNSIGNED NULL,
    external_id VARCHAR(64) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    failed_logins SMALLINT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_tenant_email (tenant_id, email),
    UNIQUE KEY uq_users_tenant_external (tenant_id, external_id),
    INDEX idx_users_residence (residence_id),
    CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_users_residence FOREIGN KEY (residence_id) REFERENCES residences(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Documents : documents soumis a signature
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    residence_id BIGINT UNSIGNED NULL,
    sothis_document_id VARCHAR(64) NOT NULL,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(200) NOT NULL,
    state ENUM(
        'en_attente_signature',
        'signature_en_cours',
        'signe',
        'signe_valide',
        'refuse',
        'expire'
    ) NOT NULL DEFAULT 'en_attente_signature',
    pdf_path VARCHAR(500) NOT NULL,
    pdf_sha256 CHAR(64) NOT NULL,
    signed_pdf_path VARCHAR(500) NULL,
    deadline DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_documents_tenant_sothis (tenant_id, sothis_document_id),
    INDEX idx_documents_tenant_state (tenant_id, state),
    INDEX idx_documents_tenant_user (tenant_id, user_id),
    CONSTRAINT fk_documents_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_documents_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_documents_residence FOREIGN KEY (residence_id) REFERENCES residences(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Champs de signature (positionnement sur le PDF)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS signature_fields (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    page SMALLINT NOT NULL,
    pos_x INT NOT NULL,
    pos_y INT NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    required TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_fields_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Signatures : signatures effectives capturees
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS signatures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    signature_field_id BIGINT UNSIGNED NULL,
    image_path VARCHAR(500) NOT NULL,
    image_sha256 CHAR(64) NOT NULL,
    signed_at DATETIME(3) NOT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    consent_method ENUM('otp_email','otp_sms') NOT NULL DEFAULT 'otp_email',
    consent_proof JSON NULL,
    INDEX idx_signatures_tenant_doc (tenant_id, document_id),
    CONSTRAINT fk_signatures_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_signatures_document FOREIGN KEY (document_id) REFERENCES documents(id),
    CONSTRAINT fk_signatures_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_signatures_field FOREIGN KEY (signature_field_id) REFERENCES signature_fields(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Magic links : connexion ou ouverture de signature par lien
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS magic_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    purpose ENUM('login','signature') NOT NULL,
    document_id BIGINT UNSIGNED NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_magic_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_magic_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- OTP : codes a usage unique pour la signature
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS otp_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    code_hash CHAR(64) NOT NULL,
    target VARCHAR(60) NOT NULL,
    attempts TINYINT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_user_target (user_id, target),
    CONSTRAINT fk_otp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Audit log : trace immuable, hash chaine par tenant
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    target_type VARCHAR(40) NULL,
    target_id VARCHAR(64) NULL,
    ip VARCHAR(45) NULL,
    context JSON NULL,
    prev_hash CHAR(64) NULL,
    row_hash CHAR(64) NOT NULL,
    created_at DATETIME(3) NOT NULL,
    INDEX idx_audit_tenant_time (tenant_id, created_at),
    CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Outbox WebSocket : messages a transmettre a SOTHIS
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ws_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    message_id CHAR(36) NOT NULL UNIQUE,
    type VARCHAR(60) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending','sent','acked','failed') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acked_at DATETIME NULL,
    INDEX idx_outbox_status (status),
    CONSTRAINT fk_outbox_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- File de mails sortants
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS mail_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    to_email VARCHAR(190) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    template VARCHAR(60) NOT NULL,
    variables JSON NULL,
    status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    INDEX idx_mail_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
