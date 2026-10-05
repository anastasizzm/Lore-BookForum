-- migrate:up
ALTER TABLE articles
    DROP CONSTRAINT articles_type_consistency,
    DROP COLUMN type;

ALTER TABLE articles
    ADD COLUMN type_id INT NOT NULL REFERENCES types (id)
        ON DELETE RESTRICT ON UPDATE CASCADE DEFAULT 1;

CREATE INDEX idx_articles_type_id ON articles (type_id);

-- migrate:down
DROP INDEX IF EXISTS idx_articles_type_id;

ALTER TABLE articles DROP COLUMN type_id;

ALTER TABLE articles
    ADD COLUMN type article_base NOT NULL DEFAULT 'content',
    ADD CONSTRAINT articles_type_consistency CHECK (
        (type = 'content'
            AND content    IS NOT NULL
            AND book_id    IS NULL
            AND page_start IS NULL
            AND page_end   IS NULL)
        OR
        (type = 'book'
            AND content    IS NULL
            AND book_id    IS NOT NULL
            AND page_start IS NOT NULL
            AND page_end   IS NOT NULL
            AND page_end >= page_start)
    );