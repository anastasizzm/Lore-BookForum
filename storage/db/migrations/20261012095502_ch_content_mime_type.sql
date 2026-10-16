-- migrate:up
ALTER TABLE files
    RENAME COLUMN content_type to mime_type;

-- migrate:down
ALTER TABLE files
    RENAME COLUMN mime_type to content_type;
