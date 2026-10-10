-- migrate:up
ALTER TABLE genres_translations
    ADD COLUMN title varchar(255) NOT NULL;

ALTER TABLE types_translations
    ADD COLUMN title varchar(255) NOT NULL;

ALTER TABLE categories_translations
    ADD COLUMN title varchar(255) NOT NULL;
-- migrate:down
ALTER TABLE genres_translations
    DROP COLUMN;

ALTER TABLE types_translations
    DROP COLUMN;

ALTER TABLE categories_translations
    DROP COLUMN;