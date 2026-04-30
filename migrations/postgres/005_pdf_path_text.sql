-- Elargit pdf_path en TEXT pour autoriser des chemins ou URL plus longs.
-- PostgreSQL : ALTER TYPE est idempotent en pratique (le cast VARCHAR->TEXT
-- est trivial et ne perd aucune donnee).
ALTER TABLE documents
    ALTER COLUMN pdf_path TYPE TEXT;
