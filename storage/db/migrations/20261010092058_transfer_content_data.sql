-- migrate:up
INSERT INTO books_content (book_id, content_id, content_type)
SELECT b.publication_id, b.content_id, f.content_type FROM books b
INNER JOIN files f ON b.content_id = f.id;

-- migrate:down
DELETE FROM books_content bc WHERE EXISTS (SELECT FROM books b WHERE bc.book_id = b.publication_id);
