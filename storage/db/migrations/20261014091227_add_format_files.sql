-- migrate:up
ALTER TABLE files
    ADD COLUMN format VARCHAR(16) NOT NULL DEFAULT 'pdf';

ALTER TABLE files
    ALTER COLUMN format DROP DEFAULT;

CREATE INDEX idx_files_category_format ON files (category, format);

-- migrate:down
ALTER TABLE files
    DROP COLUMN format;
