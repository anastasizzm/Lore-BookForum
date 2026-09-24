-- =====================================================================
-- init.sql: one-time seed data
-- Runs once on first start, as the superuser, after schema + migrations.
-- migrate.sh wraps it in a single transaction, so no BEGIN/COMMIT here.
--
-- The admin values come from .env through psql variables
-- (admin_username, admin_email, admin_pass_hash); see migrate.sh.
-- =====================================================================

INSERT INTO users (username, email, pass_hash)
VALUES (:'admin_username', :'admin_email', :'admin_pass_hash')
ON CONFLICT DO NOTHING;


INSERT INTO users_rules (user_id, is_redactor, is_admin)
SELECT u.id, TRUE, TRUE
FROM users u
WHERE lower(u.username) = lower(:'admin_username')
  AND NOT EXISTS (SELECT 1 FROM users_rules r WHERE r.user_id = u.id);


INSERT INTO genres (title) VALUES
    -- Fiction / Narrative
    ('Fantasy'),
    ('Science Fiction'),
    ('Mystery'),
    ('Thriller'),
    ('Historical Fiction'),
    ('Romance'),
    ('Literary Fiction'),
    
    -- Academic / Non-Fiction Domains
    ('Computer Science'),
    ('Mathematics'),
    ('Physics'),
    ('Biology'),
    ('Philosophy'),
    ('Economics'),
    ('Psychology'),
    ('Sociology'),
    ('History'),
    ('Engineering')
ON CONFLICT (title) DO NOTHING;


INSERT INTO categories (title) VALUES
    -- Book formats
    ('Novel'),
    ('Textbook'),
    ('Anthology'),
    ('Monograph'),
    ('Reference Work'),
    
    -- Article / Research formats
    ('Research Paper'),
    ('Review Article'),
    ('Preprint'),
    ('Technical Report'),
    ('Case Study'),
    ('Editorial')
ON CONFLICT (title) DO NOTHING;