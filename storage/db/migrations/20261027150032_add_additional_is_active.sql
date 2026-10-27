-- migrate:up
ALTER TABLE types
    ADD COLUMN deleted_at TIMESTAMPTZ,
    ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (deleted_at IS NULL) STORED;

ALTER TABLE categories
    ADD COLUMN deleted_at TIMESTAMPTZ,
    ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (deleted_at IS NULL) STORED;

ALTER TABLE genres
    ADD COLUMN deleted_at TIMESTAMPTZ,
    ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (deleted_at IS NULL) STORED;

-- migrate:down
ALTER TABLE types
    DROP COLUMN is_active,
    DROP COLUMN deleted_at;

ALTER TABLE categories
    DROP COLUMN is_active,
    DROP COLUMN deleted_at;

ALTER TABLE genres
    DROP COLUMN is_active,
    DROP COLUMN deleted_at;
