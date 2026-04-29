-- ============================================================
-- Schema initial ESPACE-PRIVATIF (version PostgreSQL).
-- Cible : Supabase / PostgreSQL 15+.
-- A jouer une seule fois sur la base de prod (psql, dashboard SQL editor, etc.).
-- ============================================================

-- Etats possibles d'un document (equivalent ENUM MySQL).
DO $$ BEGIN
    CREATE TYPE document_state AS ENUM (
        'en_attente_signature',
        'signature_en_cours',
        'signe',
        'signe_valide',
        'refuse',
        'expire'
    );
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE consent_method AS ENUM ('otp_email', 'otp_sms');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE magic_link_purpose AS ENUM ('login', 'signature');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE outbox_status AS ENUM ('pending', 'sent', 'acked', 'failed');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE mail_status AS ENUM ('pending', 'sent', 'failed');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- ----------------------------------------------------------
-- Tenants
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenants (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    sothis_api_key_hash CHAR(64) NOT NULL,
    active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------
-- Residences
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS residences (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    name VARCHAR(150) NOT NULL,
    manager_email VARCHAR(190) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_residences_tenant ON residences(tenant_id);

-- ----------------------------------------------------------
-- Users (locataires)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    residence_id BIGINT NULL REFERENCES residences(id),
    external_id VARCHAR(64) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    failed_logins SMALLINT NOT NULL DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (tenant_id, email),
    UNIQUE (tenant_id, external_id)
);
CREATE INDEX IF NOT EXISTS idx_users_residence ON users(residence_id);

-- ----------------------------------------------------------
-- Documents
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    user_id BIGINT NOT NULL REFERENCES users(id),
    residence_id BIGINT NULL REFERENCES residences(id),
    sothis_document_id VARCHAR(64) NOT NULL,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(200) NOT NULL,
    state document_state NOT NULL DEFAULT 'en_attente_signature',
    pdf_path VARCHAR(500) NOT NULL,
    pdf_sha256 CHAR(64) NOT NULL,
    signed_pdf_path VARCHAR(500) NULL,
    deadline TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (tenant_id, sothis_document_id)
);
CREATE INDEX IF NOT EXISTS idx_documents_tenant_state ON documents(tenant_id, state);
CREATE INDEX IF NOT EXISTS idx_documents_tenant_user ON documents(tenant_id, user_id);

-- Equivalent du ON UPDATE CURRENT_TIMESTAMP de MySQL : trigger PG explicite.
CREATE OR REPLACE FUNCTION set_updated_at_now()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_documents_updated_at ON documents;
CREATE TRIGGER trg_documents_updated_at
    BEFORE UPDATE ON documents
    FOR EACH ROW EXECUTE FUNCTION set_updated_at_now();

-- ----------------------------------------------------------
-- Champs de signature
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS signature_fields (
    id BIGSERIAL PRIMARY KEY,
    document_id BIGINT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    name VARCHAR(60) NOT NULL,
    page SMALLINT NOT NULL,
    pos_x INT NOT NULL,
    pos_y INT NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    required SMALLINT NOT NULL DEFAULT 1
);

-- ----------------------------------------------------------
-- Signatures
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS signatures (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    document_id BIGINT NOT NULL REFERENCES documents(id),
    user_id BIGINT NOT NULL REFERENCES users(id),
    signature_field_id BIGINT NULL REFERENCES signature_fields(id),
    image_path VARCHAR(500) NOT NULL,
    image_sha256 CHAR(64) NOT NULL,
    signed_at TIMESTAMP(3) NOT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    consent_method consent_method NOT NULL DEFAULT 'otp_email',
    consent_proof JSONB NULL
);
CREATE INDEX IF NOT EXISTS idx_signatures_tenant_doc ON signatures(tenant_id, document_id);

-- ----------------------------------------------------------
-- Magic links
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS magic_links (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    user_id BIGINT NOT NULL REFERENCES users(id),
    token_hash CHAR(64) NOT NULL UNIQUE,
    purpose magic_link_purpose NOT NULL,
    document_id BIGINT NULL,
    expires_at TIMESTAMP NOT NULL,
    consumed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------
-- OTP
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS otp_codes (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    user_id BIGINT NOT NULL REFERENCES users(id),
    code_hash CHAR(64) NOT NULL,
    target VARCHAR(60) NOT NULL,
    attempts SMALLINT NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    consumed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_otp_user_target ON otp_codes(user_id, target);

-- ----------------------------------------------------------
-- Audit log
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    user_id BIGINT NULL,
    action VARCHAR(60) NOT NULL,
    target_type VARCHAR(40) NULL,
    target_id VARCHAR(64) NULL,
    ip VARCHAR(45) NULL,
    context JSONB NULL,
    prev_hash CHAR(64) NULL,
    row_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP(3) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_audit_tenant_time ON audit_log(tenant_id, created_at);

-- ----------------------------------------------------------
-- Outbox WebSocket
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ws_outbox (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL REFERENCES tenants(id),
    message_id CHAR(36) NOT NULL UNIQUE,
    type VARCHAR(60) NOT NULL,
    payload JSONB NOT NULL,
    status outbox_status NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acked_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS idx_outbox_status ON ws_outbox(status);

-- ----------------------------------------------------------
-- Mail queue
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS mail_queue (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NULL,
    to_email VARCHAR(190) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    template VARCHAR(60) NOT NULL,
    variables JSONB NULL,
    status mail_status NOT NULL DEFAULT 'pending',
    attempts SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS idx_mail_status ON mail_queue(status);
