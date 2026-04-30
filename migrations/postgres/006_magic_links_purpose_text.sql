-- Elargit purpose en VARCHAR pour accepter "reset_password".
-- PostgreSQL : on cast l'enum vers text via USING.
ALTER TABLE magic_links
    ALTER COLUMN purpose TYPE VARCHAR(40) USING purpose::text;
