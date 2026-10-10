-- migrate:up
INSERT INTO genres_translations (genre_id, code, title)
SELECT g.id, 'en', g.title FROM genres g;

-- migrate:down
DELETE FROM genres_translations gt WHERE EXISTS (SELECT 1 FROM genres g WHERE g.id = gt.genre_id);
