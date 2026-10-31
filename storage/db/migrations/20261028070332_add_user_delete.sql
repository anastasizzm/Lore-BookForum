-- migrate:up
ALTER TABLE users
    ADD COLUMN deleted_at TIMESTAMPTZ,
    ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (deleted_at IS NULL) STORED;

DROP INDEX IF EXISTS uq_users_username_lower;
DROP INDEX IF EXISTS uq_users_email_lower;

CREATE UNIQUE INDEX uq_users_username_lower
    ON users (lower(username))
    WHERE is_active;

CREATE UNIQUE INDEX uq_users_email_lower
    ON users (lower(email))
    WHERE is_active;

-- migrate:down
ALTER TABLE users
    DROP COLUMN is_active,
    DROP COLUMN deleted_at;

DROP INDEX IF EXISTS uq_users_username_lower;
DROP INDEX IF EXISTS uq_users_email_lower;

CREATE UNIQUE INDEX uq_users_username_lower ON users (lower(username));
CREATE UNIQUE INDEX uq_users_email_lower    ON users (lower(email));