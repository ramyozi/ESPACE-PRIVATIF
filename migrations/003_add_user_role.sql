-- Ajout d'un role utilisateur. Garde retro-compatibilite : par defaut "user".
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'user';

-- Index optionnel pour filtrer rapidement les admins par tenant.
CREATE INDEX IF NOT EXISTS idx_users_tenant_role ON users(tenant_id, role);
