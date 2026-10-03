-- migrate:up
ALTER TABLE categories
ADD COLUMN created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW();

ALTER TABLE genres
ADD COLUMN created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW();

-- migrate:down
ALTER TABLE categories
DROP COLUMN created_at;

ALTER TABLE genres
DROP COLUMN created_at;