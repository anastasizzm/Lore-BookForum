-- migrate:up
ALTER TABLE genres_translations
    ADD PRIMARY KEY (genre_id, code);

ALTER TABLE categories_translations
    ADD PRIMARY KEY (category_id, code);

ALTER TABLE types_translations
    ADD PRIMARY KEY (type_id, code);

ALTER TABLE genres
    DROP COLUMN title;

ALTER TABLE categories
    DROP COLUMN title;

ALTER TABLE types
    DROP COLUMN title;

-- migrate:down
ALTER TABLE genres_translations
    DROP CONSTRAINT genres_translations_pkey;

ALTER TABLE categories_translations
    DROP CONSTRAINT categories_translations_pkey;

ALTER TABLE types_translations
    DROP CONSTRAINT types_translations_pkey;

ALTER TABLE genres 
    ADD COLUMN title VARCHAR(255) NOT NULL;

ALTER TABLE categories 
    ADD COLUMN title VARCHAR(255) NOT NULL;

ALTER TABLE types 
    ADD COLUMN title VARCHAR(255) NOT NULL;