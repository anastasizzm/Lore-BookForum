-- migrate:up
ALTER TABLE publications_users
    RENAME COLUMN deleted_at TO terminated_at;

ALTER TABLE publications_users
    ALTER COLUMN terminated_at SET DEFAULT 'infinity'::TIMESTAMPTZ,
    DROP COLUMN is_active;

CREATE INDEX idx_publications_users_terminated_at ON publications_users (terminated_at);

-- migrate:down
ALTER TABLE publications_users
    ADD COLUMN is_active boolean DEFAULT TRUE,
    RENAME COLUMN terminated_at TO deleted_at;
