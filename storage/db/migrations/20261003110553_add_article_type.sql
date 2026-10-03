-- migrate:up
CREATE TABLE types (
    id     SERIAL PRIMARY KEY,
    title  VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

INSERT INTO types (title) VALUES ('Review');

-- migrate:down
DROP TABLE types;