-- migrate:up
ALTER TABLE articles
ALTER COLUMN type_id DROP DEFAULT;

-- migrate:down
ALTER TABLE articles
ALTER COLUMN type_id SET DEFAULT 1;
