-- migrate:up
ALTER TABLE articles
    ADD COLUMN content_mode VARCHAR(16) GENERATED ALWAYS AS (
        CASE WHEN book_id IS NULL THEN 'text'
        ELSE 'book'
        END
    ) STORED;

ALTER TABLE articles
    ADD CONSTRAINT chk_articles_content_mode CHECK (
        (content_mode = 'text'
            AND content IS NOT NULL
            AND book_id IS NULL
            AND page_start = 0
            AND page_end = 0)
        OR
        (content_mode = 'book'
            AND content IS NULL
            AND book_id IS NOT NULL
            AND page_start != 0
            AND page_end != 0)
    );

-- migrate:down
ALTER TABLE articles
    DROP CONSTRAINT chk_articles_content_mode,
    DROP COLUMN content_mode;