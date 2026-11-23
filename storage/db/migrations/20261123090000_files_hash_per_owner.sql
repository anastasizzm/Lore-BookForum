-- Hash deduplication is scoped to the uploader and category: two users may
-- legitimately upload byte-identical files without sharing one file row.
-- migrate:up
DROP INDEX IF EXISTS files_hash_uq;
CREATE UNIQUE INDEX files_hash_owner_category_uq
    ON files (hash, uploaded_by, category);

-- migrate:down
DROP INDEX IF EXISTS files_hash_owner_category_uq;
CREATE UNIQUE INDEX files_hash_uq ON files (hash);
