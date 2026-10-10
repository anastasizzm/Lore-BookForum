-- migrate:up
INSERT INTO categories_translations (category_id, code, title)
SELECT g.id, 'en', g.title FROM categories g;

-- migrate:down
DELETE FROM categories_translations gt WHERE EXISTS (SELECT 1 FROM categories g WHERE g.id = gt.category_id);
