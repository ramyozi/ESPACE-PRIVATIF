-- Tracabilite : qui a cree le document.
-- NULL = depot SOTHIS (s2s), sinon id de l'admin qui a declenche l'upload.
ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS created_by BIGINT NULL;

CREATE INDEX IF NOT EXISTS idx_documents_created_by ON documents(created_by);
