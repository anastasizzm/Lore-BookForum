-- migrate:up
DROP TABLE publications_authors;
DROP TABLE publications_users;

-- migrate:down
REATE TABLE IF NOT EXISTS publications_authors (
    publication_id BIGINT NOT NULL REFERENCES publications(id) ON DELETE CASCADE ON UPDATE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    terminated_at TIMESTAMPTZ NOT NULL DEFAULT 'infinity'::TIMESTAMPTZ,
    PRIMARY KEY (publication_id, user_id)
);

CREATE INDEX idx_publications_authors_teminated ON publications_authors (terminated_at);

CREATE TABLE publications_users (
    user_id         BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    publication_id  BIGINT NOT NULL REFERENCES publications(id) ON DELETE CASCADE ON UPDATE CASCADE,
    is_redactor     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    terminated_at   TIMESTAMPTZ NOT NULL DEFAULT 'infinity',

    CONSTRAINT pubs_users_pk PRIMARY KEY (user_id, publication_id)
);
