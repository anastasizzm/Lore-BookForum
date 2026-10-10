-- migrate:up
CREATE TABLE IF NOT EXISTS genres_translations
(
    genre_id INT NOT NULL REFERENCES genres(id) ON DELETE CASCADE ON UPDATE CASCADE,
    code CHAR(2) NOT NULL REFERENCES languages(code) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- migrate:down
DROP TABLE IF EXISTS genres_translations;
