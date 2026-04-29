-- ============================================================
-- Donnees de base pour la demo (version PostgreSQL).
-- Les utilisateurs sont crees via bin/seed-users.php pour avoir
-- des hash bcrypt corrects (depend de password_hash cote PHP).
-- ============================================================

INSERT INTO tenants (id, code, name, sothis_api_key_hash, active)
VALUES (1, 'REALSOFT', 'Realsoft Immobilier', encode(digest('sothis-shared-key-dev', 'sha256'), 'hex'), 1)
ON CONFLICT (code) DO NOTHING;

INSERT INTO residences (id, tenant_id, name, manager_email)
VALUES (1, 1, 'Residence Les Lilas - Lyon 7', 'gestion.lilas@realsoft.fr')
ON CONFLICT DO NOTHING;

-- Aligne les sequences sur les ids inseres manuellement.
SELECT setval('tenants_id_seq', GREATEST((SELECT MAX(id) FROM tenants), 1));
SELECT setval('residences_id_seq', GREATEST((SELECT MAX(id) FROM residences), 1));
