-- migrate:up
ALTER TABLE roles_users 
    DROP COLUMN granted_by_id;

-- migrate:down
ALTER TABLE roles_users
    ADD COLUMN granted_by_id BIGINT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE;

UPDATE roles_users SET granted_by_id = user_id;

ALTER TABLE roles_users
    ALTER COLUMN granted_by_id SET NOT NULL;
