-- migrate:up
ALTER TABLE publications
    ADD COLUMN owner_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE DEFAULT 11;

ALTER TABLE publications
    ALTER COLUMN owner_id DROP DEFAULT;

-- migrate:down
ALTER TABLE publications
    DROP COLUMN owner_id;
