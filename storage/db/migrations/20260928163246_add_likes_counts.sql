-- migrate:up
ALTER TABLE comments
ADD COLUMN likes_count INT NOT NULL DEFAULT 0,
ADD COLUMN comments_count INT NOT NULL DEFAULT 0;

ALTER TABLE publications
ADD COLUMN comments_count INT NOT NULL DEFAULT 0;

-- migrate:down
ALTER TABLE comments
DROP COLUMN likes_count,
DROP COLUMN comments_count;

ALTER TABLE publications
DROP COLUMN comments_count;

