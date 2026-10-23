-- migrate:up
ALTER TABLE users_read
    RENAME COLUMN is_closed TO is_finished;

ALTER TABLE users_read
    ADD COLUMN format VARCHAR(16) NOT NULL,
    DROP COLUMN page_num,
    ADD COLUMN position JSONB NOT NULL DEFAULT '{"pageNumber": 1}',
    ADD COLUMN created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    DROP CONSTRAINT read_status_pk;

ALTER TABLE users_read 
    ADD PRIMARY KEY (user_id, publication_id, format);

CREATE INDEX idx_users_read_recent
    ON users_read (user_id, last_read_at DESC);

CREATE INDEX idx_users_read_finished
    ON users_read (user_id, publication_id)
    WHERE is_finished = TRUE;

-- migrate:down
DROP INDEX IF EXISTS idx_users_read_finished;
DROP INDEX IF EXISTS idx_users_read_recent;

ALTER TABLE users_read
    DROP CONSTRAINT users_read_pkey;

ALTER TABLE users_read
    ADD CONSTRAINT users_read_pkey PRIMARY KEY (user_id, publication_id);

ALTER TABLE users_read
    RENAME COLUMN is_finished TO is_closed;

ALTER TABLE users_read
    ADD COLUMN page_num SMALLINT NOT NULL DEFAULT 1,
    DROP COLUMN IF EXISTS created_at,
    DROP COLUMN IF EXISTS position,
    DROP COLUMN IF EXISTS format;

