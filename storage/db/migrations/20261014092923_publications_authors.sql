-- migrate:up
CREATE TABLE IF NOT EXISTS publications_authors (
    publication_id BIGINT NOT NULL REFERENCES publications(id) ON DELETE CASCADE ON UPDATE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    terminated_at TIMESTAMPTZ NOT NULL DEFAULT 'infinity'::TIMESTAMPTZ,
    PRIMARY KEY (publication_id, user_id)
);

CREATE INDEX idx_publications_authors_teminated ON publications_authors (terminated_at);

-- migrate:down
DROP TABLE publications_authors;
