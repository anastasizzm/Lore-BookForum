-- migrate:up
CREATE TABLE IF NOT EXISTS types_translations
(
    type_id INT NOT NULL REFERENCES types(id) ON DELETE CASCADE ON UPDATE CASCADE,
    code CHAR(2) NOT NULL REFERENCES languages(code) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- migrate:down
DROP TABLE IF EXISTS types_translations;
