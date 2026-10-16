-- migrate:up
ALTER TABLE files RENAME COLUMN object_key TO path;

ALTER TABLE files 
    ADD COLUMN category VARCHAR(32) NOT NULL DEFAULT 'cover',
    ADD COLUMN original_name VARCHAR(500) NOT NULL DEFAULT '',
    DROP COLUMN width,
    DROP COLUMN height;

CREATE INDEX idx_files_category ON files (category);
-- migrate:down
ALTER TABLE files RENAME COLUMN path TO object_key;


ALTER TABLE files
    DROP COLUMN category,
    DROP COLUMN original_name,
    ADD COLUMN width INT NULL,
    ADD COLUMN height INT NULL;

DROP INDEX idx_files_category ON files;