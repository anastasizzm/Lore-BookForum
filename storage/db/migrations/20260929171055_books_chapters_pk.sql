-- migrate:up
ALTER TABLE books
    ADD CONSTRAINT books_publication_id_unique UNIQUE (publication_id);

-- 2. Снять старый FK из books_chapters (смотрел на books.id)
ALTER TABLE books_chapters
    DROP CONSTRAINT IF EXISTS books_chapters_book_id_fkey;

ALTER TABLE articles
    DROP CONSTRAINT IF EXISTS articles_book_id_fkey;

ALTER TABLE articles 
    ALTER COLUMN book_id DROP NOT NULL;

ALTER TABLE articles
    ADD CONSTRAINT articles_book_id_fkey
    FOREIGN KEY (book_id) REFERENCES books(publication_id) ON DELETE SET NULL;

-- 3. Пересоздать FK на books.publication_id
ALTER TABLE books_chapters
    ADD CONSTRAINT books_chapters_book_id_fkey
    FOREIGN KEY (book_id) REFERENCES books(publication_id) ON DELETE CASCADE;

-- 4. Снять старый PK books
ALTER TABLE books
    DROP CONSTRAINT books_pkey;

-- 5. publication_id — новый PK
ALTER TABLE books
    ALTER COLUMN publication_id SET NOT NULL;
ALTER TABLE books
    ADD CONSTRAINT books_pkey PRIMARY KEY (publication_id);

-- 6. Временный UNIQUE больше не нужен — PK уже даёт уникальность
ALTER TABLE books
    DROP CONSTRAINT books_publication_id_unique;

-- 7. Удалить старую колонку id
--    Sequence books_id_seq (если была) уйдёт автоматически, потому что OWNED BY books.id
ALTER TABLE books
    DROP COLUMN id;

-- migrate:down
ALTER TABLE books_chapters
    DROP CONSTRAINT IF EXISTS books_chapters_book_id_fkey;

ALTER TABLE books
    DROP CONSTRAINT books_pkey;

ALTER TABLE books
    ADD COLUMN id BIGSERIAL;

UPDATE books SET id = publication_id;

ALTER TABLE books
    ADD CONSTRAINT books_pkey PRIMARY KEY (id);

ALTER TABLE books_chapters
    ADD CONSTRAINT books_chapters_book_id_fkey
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE;
