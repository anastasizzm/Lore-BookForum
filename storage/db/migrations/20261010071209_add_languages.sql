-- migrate:up
CREATE TABLE IF NOT EXISTS languages 
(
    code CHAR(2) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT true
);
INSERT INTO languages (code) VALUES ('ru'), ('en');

-- migrate:down
DROP TABLE IF EXISTS languages;
