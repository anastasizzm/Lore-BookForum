-- migrate:up
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX publications_title_trgm_idx
    ON publications
    USING GIN (title gin_trgm_ops);

-- migrate:down
DROP INDEX IF EXISTS publications_title_trgm_idx;