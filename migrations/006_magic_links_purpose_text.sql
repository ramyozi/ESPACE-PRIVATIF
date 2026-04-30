-- Elargit purpose en VARCHAR pour accepter "reset_password" sans toucher a l'enum.
-- MySQL : MODIFY COLUMN, idempotent.
ALTER TABLE magic_links
    MODIFY COLUMN purpose VARCHAR(40) NOT NULL;
