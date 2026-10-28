-- migrate:up
ALTER TABLE languages
    ADD COLUMN created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ADD COLUMN deleted_at TIMESTAMPTZ,
    DROP COLUMN is_active;

ALTER TABLE languages
    ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (deleted_at IS NULL) STORED;

-- migrate:down
ALTER TABLE languages   
    DROP COLUMN is_active,
    DROP COLUMN created_at;

ALTER TABLE languages
    DROP COLUMN deleted_at,
    ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE;
