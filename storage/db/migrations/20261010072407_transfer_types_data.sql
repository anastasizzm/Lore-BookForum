-- migrate:up
INSERT INTO types_translations (type_id, code, title)
SELECT g.id, 'en', g.title FROM types g;

-- migrate:down
DELETE FROM types_translations gt WHERE EXISTS (SELECT 1 FROM types g WHERE g.id = gt.type_id);
