-- migrate:up
CREATE TABLE IF NOT EXISTS books_content
(
    book_id BIGINT NOT NULL REFERENCES books(publication_id) ON DELETE CASCADE ON UPDATE CASCADE,
    content_id UUID NOT NULL REFERENCES files(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    content_type TEXT NOT NULL,
    PRIMARY KEY (book_id, content_type) 
);

-- migrate:down
DROP TABLE books_content;
