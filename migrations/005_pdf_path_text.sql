-- Elargit pdf_path en TEXT pour autoriser des chemins ou URL plus longs.
-- MySQL : on passe VARCHAR(500) -> TEXT. Idempotent grace a IF EXISTS.
ALTER TABLE documents
    MODIFY COLUMN pdf_path TEXT NOT NULL;
