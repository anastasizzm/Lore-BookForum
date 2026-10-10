-- migrate:up
ALTER TABLE books
    DROP COLUMN content_id;

-- migrate:down
ALTER TABLE books
    ADD COLUMN content_id UUID REFERENCES files(id) ON DELETE RESTRICT ON UPDATE CASCADE;
