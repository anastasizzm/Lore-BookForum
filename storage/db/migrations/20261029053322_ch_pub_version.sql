-- migrate:up
ALTER TABLE publications 
    DROP COLUMN version;

ALTER TABLE articles
    ADD COLUMN version INT NOT NULL DEFAULT 1;

-- migrate:down
ALTER TABLE publications
    ADD COLUMN version INT NOT NULL DEFAULT 1;

ALTER TABLE articles
    DROP COLUMN version;