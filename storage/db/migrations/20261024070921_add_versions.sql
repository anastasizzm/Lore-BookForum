-- migrate:up
ALTER TABLE roles_permissions ADD COLUMN version INT NOT NULL DEFAULT 1;
ALTER TABLE roles_users ADD COLUMN version INT NOT NULL DEFAULT 1;
ALTER TABLE publications ADD COLUMN version INT NOT NULL DEFAULT 1;

-- migrate:down
ALTER TABLE roles_permissions DROP COLUMN version;
ALTER TABLE roles_users DROP COLUMN version;
ALTER TABLE publications DROP COLUMN version;

