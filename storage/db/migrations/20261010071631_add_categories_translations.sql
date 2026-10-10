-- migrate:up
CREATE TABLE IF NOT EXISTS categories_translations
(
    category_id INT NOT NULL REFERENCES categories(id) ON DELETE CASCADE ON UPDATE CASCADE,
    code CHAR(2) NOT NULL REFERENCES languages(code) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- migrate:down
DROP TABLE IF EXISTS categories_translations;
