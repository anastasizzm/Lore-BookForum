-- migrate:up
CREATE UNIQUE INDEX roles_users_valid_uq
    ON roles_users (publication_id, user_id, role_id)
    NULLS NOT DISTINCT
    WHERE is_valid;

-- migrate:down
ALTER TABLE roles_users
    DROP CONSTRAINT roles_users_valid_uq;
