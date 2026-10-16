-- migrate:up
CREATE TYPE file_status AS ENUM ('active', 'pending');

ALTER TABLE files
    ADD COLUMN status file_status NOT NULL DEFAULT 'active',
    DROP COLUMN is_active;
-- migrate:down
ALTER TABLE files
    DROP COLUMN status,
    ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE;

DROP TYPE file_status;
