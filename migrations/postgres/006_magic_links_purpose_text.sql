-- Elargit purpose en VARCHAR pour accepter "reset_password".
-- L'ENUM magic_link_purpose initial ne contient que 'login' et 'signature'
-- (cf. 001_init.sql) et rejette donc tout INSERT avec 'reset_password',
-- ce qui faisait planter /forgot-password en 500 cote API.
--
-- Idempotent : on ne fait l'ALTER que si la colonne est encore l'ENUM.
-- Permet de rejouer la migration sans erreur sur une base deja patchee.
DO $$
DECLARE
    col_type text;
BEGIN
    SELECT udt_name INTO col_type
      FROM information_schema.columns
     WHERE table_name = 'magic_links'
       AND column_name = 'purpose';

    IF col_type = 'magic_link_purpose' THEN
        ALTER TABLE magic_links
            ALTER COLUMN purpose TYPE VARCHAR(40) USING purpose::text;
    END IF;
END $$;
