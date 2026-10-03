-- migrate:up

ALTER TABLE articles
    DROP CONSTRAINT IF EXISTS articles_page_start_check,
    DROP CONSTRAINT IF EXISTS articles_page_end_check;

UPDATE articles SET page_start = 0 WHERE page_start IS NULL;
UPDATE articles SET page_end   = 0 WHERE page_end   IS NULL;

ALTER TABLE articles
    ADD CONSTRAINT articles_page_start_check CHECK (page_start >= 0),
    ADD CONSTRAINT articles_page_end_check   CHECK (page_end   >= 0),
    ALTER COLUMN page_start SET DEFAULT 0,
    ALTER COLUMN page_start SET NOT NULL,
    ALTER COLUMN page_end   SET DEFAULT 0,
    ALTER COLUMN page_end   SET NOT NULL;

-- migrate:down

ALTER TABLE articles
    DROP CONSTRAINT IF EXISTS articles_page_start_check,
    DROP CONSTRAINT IF EXISTS articles_page_end_check;

ALTER TABLE articles
    ALTER COLUMN page_start DROP DEFAULT,
    ALTER COLUMN page_start DROP NOT NULL,
    ALTER COLUMN page_end   DROP DEFAULT,
    ALTER COLUMN page_end   DROP NOT NULL,
    ADD CONSTRAINT articles_page_start_check CHECK (page_start > 0),
    ADD CONSTRAINT articles_page_end_check   CHECK (page_end   > 0);