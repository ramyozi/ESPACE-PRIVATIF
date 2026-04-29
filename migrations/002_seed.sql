-- ============================================================
-- Donnees de base pour la demo : tenant, residence.
-- Les utilisateurs sont crees via bin/seed-users.php pour avoir
-- des hash bcrypt corrects (depend de password_hash cote PHP).
-- ============================================================

INSERT INTO tenants (id, code, name, sothis_api_key_hash, active)
VALUES (1, 'REALSOFT', 'Realsoft Immobilier', SHA2('sothis-shared-key-dev', 256), 1);

INSERT INTO residences (id, tenant_id, name, manager_email)
VALUES (1, 1, 'Residence Les Lilas - Lyon 7', 'gestion.lilas@realsoft.fr');
