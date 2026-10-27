-- migrate:up
ALTER TABLE user_roles
    ADD COLUMN created_at TIMESTAMPTZ NOT NULL DEFAULT NOW();
    
-- migrate:down
ALTER TABLE user_roles
    DROP COLUMN created_at;
