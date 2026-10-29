-- migrate:up
ALTER TABLE user_roles
    ADD COLUMN is_public BOOLEAN NOT NULL DEFAULT TRUE;

-- migrate:down
ALTER TABLE user_roles
    DROP COLUMN is_public;
