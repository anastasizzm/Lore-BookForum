-- migrate:up
ALTER TABLE publications
    ADD COLUMN owner_id BIGINT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE;

UPDATE publications
    SET owner_id = creator_id;

ALTER TABLE publications
    ALTER COLUMN owner_id SET NOT NULL;

-- migrate:down
ALTER TABLE publications
    DROP COLUMN owner_id;
