-- migrate:up
ALTER TABLE books_content
    DROP CONSTRAINT books_content_pkey;

ALTER TABLE books_content
    ADD COLUMN format VARCHAR(16) NOT NULL DEFAULT 'pdf',
    ADD PRIMARY KEY (book_id, format),
    DROP COLUMN content_type;

ALTER TABLE books_content
    ALTER COLUMN format DROP DEFAULT;

-- migrate:down
ALTER TABLE books_content
    DROP CONSTRAINT books_content_pkey;

ALTER TABLE books_content
    ADD COLUMN content_type TEXT NOT NULL DEFAULT 'application/pdf',
    ADD PRIMARY KEY (book_id, content_type),
    DROP COLUMN format;

ALTER TABLE books_content
    ALTER COLUMN content_type DROP DEFAULT;