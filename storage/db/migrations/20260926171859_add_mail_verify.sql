-- migrate:up
ALTER TABLE users
ADD is_verified BOOLEAN NOT NULL DEFAULT FALSE,
ADD is_blocked BOOLEAN NOT NULL DEFAULT FALSE,
DROP COLUMN is_active;

-- migrate:down
ALTER TABLE users
DROP COLUMN is_verified,
DROP COLUMN is_blocked,
ADD is_active BOOLEAN NOT NULL DEFAULT TRUE;

