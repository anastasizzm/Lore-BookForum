-- migrate:up
ALTER TABLE files
    ALTER COLUMN category DROP DEFAULT,
    ALTER COLUMN original_name DROP DEFAULT;

-- migrate:down
ALTER TABLE files
    ALTER COLUMN category SET DEFAULT 'cover',
    ALTER COLUMN original_name SET DEFAULT '';
