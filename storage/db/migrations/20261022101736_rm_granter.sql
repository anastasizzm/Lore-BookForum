-- migrate:up
ALTER TABLE roles_users 
    DROP COLUMN granted_by_id;

-- migrate:down
ALTER TABLE roles_users
    ADD COLUMN granted_by_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE;
