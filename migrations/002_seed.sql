-- ============================================================
-- Donnees de demo pour les tests locaux
-- Mot de passe pour les deux locataires : "demo1234"
-- (hash bcrypt genere statiquement)
-- ============================================================

INSERT INTO tenants (id, code, name, sothis_api_key_hash, active)
VALUES (1, 'T-001', 'Agence Demo', SHA2('sothis-shared-key-dev', 256), 1);

INSERT INTO residences (id, tenant_id, name, manager_email)
VALUES (1, 1, 'Residence des Lilas', 'manager+lilas@example.test');

-- Le hash correspond a "demo1234"
INSERT INTO users (id, tenant_id, residence_id, external_id, email, password_hash, first_name, last_name, phone)
VALUES
  (1, 1, 1, 'LOC-1001', 'alice@example.test',
   '$2y$12$9V1QbnL7Uo0Y2yDqJqf3IO9rLqVQwYy3K/F8m8Q3o7n7hF1ZPq6gK',
   'Alice', 'Martin', '+33600000001'),
  (2, 1, 1, 'LOC-1002', 'bob@example.test',
   '$2y$12$9V1QbnL7Uo0Y2yDqJqf3IO9rLqVQwYy3K/F8m8Q3o7n7hF1ZPq6gK',
   'Bob', 'Durand', '+33600000002');

INSERT INTO documents (id, tenant_id, user_id, residence_id, sothis_document_id, type, title, state, pdf_path, pdf_sha256, deadline)
VALUES
  (1, 1, 1, 1, 'DOC-2026-0001', 'bail', 'Bail residence Lilas - Alice',
   'en_attente_signature', '/storage/pdfs/demo-bail-alice.pdf',
   REPEAT('a', 64), DATE_ADD(NOW(), INTERVAL 14 DAY)),
  (2, 1, 2, 1, 'DOC-2026-0002', 'avenant', 'Avenant loyer 2026 - Bob',
   'en_attente_signature', '/storage/pdfs/demo-avenant-bob.pdf',
   REPEAT('b', 64), DATE_ADD(NOW(), INTERVAL 14 DAY));

INSERT INTO signature_fields (document_id, name, page, pos_x, pos_y, width, height, required)
VALUES
  (1, 'signature_locataire', 3, 120, 600, 200, 60, 1),
  (2, 'signature_locataire', 1, 120, 500, 200, 60, 1);
