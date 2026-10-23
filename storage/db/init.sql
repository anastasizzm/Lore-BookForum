-- =====================================================================
-- init.sql: one-time seed data
-- Runs once on first start, as the superuser, after schema + migrations,
-- against a database with NO rows yet anywhere (languages included).
-- migrate.sh wraps it in a single transaction, so no BEGIN/COMMIT here.
-- Every block below is written to be safe to re-run (guarded by
-- NOT EXISTS / ON CONFLICT), in case this file is ever run by hand.
--
-- The admin values come from .env through psql variables
-- (admin_username, admin_email, admin_pass_hash); see migrate.sh.
-- =====================================================================

INSERT INTO languages (code, is_active) VALUES
    ('en', true),
    ('ru', true);


INSERT INTO user_roles (id, color, is_system) VALUES
    (0, '6B7280', true),
    (1, 'B91C1C', true);

INSERT INTO user_roles_translations (role_id, code, title) VALUES
    (0, 'en', 'User'),
    (0, 'ru', 'Пользователь'),
    (1, 'en', 'Head Administrator'),
    (1, 'ru', 'Главный администратор');

INSERT INTO roles_permissions (role_id, publication_id, 
  pub_create, pub_update_meta, pub_update_content, pub_delete, pub_read, pub_manage_ppl, pub_manage_roles,
  post_create, post_update, post_delete,
  sys_manage_accounts, sys_manage_additionals, sys_manage_ppl, sys_manage_roles, 
  authority_rank
) VALUES 
(0, NULL, true, false, false, false, false, false, false, true, false, false, false, false, false, false, 2),
(1, NULL, true, true, true, true, true, true, true, true, true, true, true, true, true, true, 2000000);

INSERT INTO users (username, email, pass_hash, is_verified)
VALUES (:'admin_username', :'admin_email', :'admin_pass_hash', true);

INSERT INTO profiles (user_id, name, surname, avatar)
SELECT u.id, 'Admin', 'Adminovich', 'default'
FROM users u
WHERE lower(u.username) = lower(:'admin_username')
  AND NOT EXISTS (SELECT 1 FROM profiles p WHERE p.user_id = u.id);

INSERT INTO roles_users (user_id, publication_id, role_id, granted_by_id)
SELECT u.id, NULL, 1, u.id
FROM users u
WHERE lower(u.username) = lower(:'admin_username')
  AND NOT EXISTS (SELECT 1 FROM roles_users r WHERE r.user_id = u.id);

-- ---------------------------------------------------------------------
-- Genres: base rows (id only - title now lives in genres_translations),
-- then English + Russian titles, in the same insertion order your
-- existing add_ru_translations migration already assumes (ids 1-17).
-- Guarded by English title so re-running this file is a no-op.
-- ---------------------------------------------------------------------
WITH ordered (seq, title_en, title_ru) AS (
    VALUES
        (1,  'Fantasy',             'Фэнтези'),
        (2,  'Science Fiction',     'Научная фантастика'),
        (3,  'Mystery',             'Детектив'),
        (4,  'Thriller',            'Триллер'),
        (5,  'Historical Fiction',  'Историческая проза'),
        (6,  'Romance',             'Любовный роман'),
        (7,  'Literary Fiction',    'Литературная проза'),
        (8,  'Computer Science',    'Информатика'),
        (9,  'Mathematics',         'Математика'),
        (10, 'Physics',             'Физика'),
        (11, 'Biology',             'Биология'),
        (12, 'Philosophy',          'Философия'),
        (13, 'Economics',           'Экономика'),
        (14, 'Psychology',          'Психология'),
        (15, 'Sociology',           'Социология'),
        (16, 'History',             'История'),
        (17, 'Engineering',         'Инженерия')
), missing AS (
    SELECT o.* FROM ordered o
    WHERE NOT EXISTS (
        SELECT 1 FROM genres_translations gt
        WHERE gt.code = 'en' AND gt.title = o.title_en
    )
), inserted AS (
    INSERT INTO genres (id)
    SELECT nextval(pg_get_serial_sequence('genres', 'id'))
    FROM missing
    ORDER BY seq
    RETURNING id
), numbered AS (
    SELECT row_number() OVER () AS rn, id FROM inserted
), rows AS (
    SELECT row_number() OVER (ORDER BY seq) AS rn, title_en, title_ru FROM missing
)
INSERT INTO genres_translations (genre_id, code, title)
SELECT n.id, l.code, l.title
FROM numbered n
JOIN rows r ON r.rn = n.rn
CROSS JOIN LATERAL (VALUES ('en', r.title_en), ('ru', r.title_ru)) AS l(code, title);


-- ---------------------------------------------------------------------
-- Categories: same pattern, ids 1-11
-- ---------------------------------------------------------------------
WITH ordered (seq, title_en, title_ru) AS (
    VALUES
        (1,  'Novel',            'Роман'),
        (2,  'Textbook',         'Учебник'),
        (3,  'Anthology',        'Антология'),
        (4,  'Monograph',        'Монография'),
        (5,  'Reference Work',   'Справочное издание'),
        (6,  'Research Paper',   'Научная статья'),
        (7,  'Review Article',   'Обзорная статья'),
        (8,  'Preprint',         'Препринт'),
        (9,  'Technical Report', 'Технический отчёт'),
        (10, 'Case Study',       'Тематическое исследование'),
        (11, 'Editorial',        'Редакционная статья')
), missing AS (
    SELECT o.* FROM ordered o
    WHERE NOT EXISTS (
        SELECT 1 FROM categories_translations ct
        WHERE ct.code = 'en' AND ct.title = o.title_en
    )
), inserted AS (
    INSERT INTO categories (id)
    SELECT nextval(pg_get_serial_sequence('categories', 'id'))
    FROM missing
    ORDER BY seq
    RETURNING id
), numbered AS (
    SELECT row_number() OVER () AS rn, id FROM inserted
), rows AS (
    SELECT row_number() OVER (ORDER BY seq) AS rn, title_en, title_ru FROM missing
)
INSERT INTO categories_translations (category_id, code, title)
SELECT n.id, l.code, l.title
FROM numbered n
JOIN rows r ON r.rn = n.rn
CROSS JOIN LATERAL (VALUES ('en', r.title_en), ('ru', r.title_ru)) AS l(code, title);


-- ---------------------------------------------------------------------
-- Types: same pattern as genres/categories above, ids 1-114 matching
-- your existing add_ru_translations migration. English titles are a
-- back-translation from the Russian text already in that migration -
-- review before relying on this in production (see chat for the two
-- disambiguated collisions: id 1/26 "Review"/"Overview", id 41/93
-- "Abstract"/"Precis").
-- ---------------------------------------------------------------------
WITH ordered (seq, title_en, title_ru) AS (
    VALUES
        (1, 'Review', 'Обзор'),
        (2, 'Research Article', 'Научная статья'),
        (3, 'Systematic Review', 'Систематический обзор'),
        (4, 'Meta-Analysis', 'Метаанализ'),
        (5, 'Case Study', 'Тематическое исследование'),
        (6, 'Short Communication', 'Краткое сообщение'),
        (7, 'Letter', 'Письмо'),
        (8, 'Editorial', 'Редакционная статья'),
        (9, 'Commentary', 'Комментарий'),
        (10, 'Perspective', 'Перспектива'),
        (11, 'Opinion', 'Мнение'),
        (12, 'Conference Paper', 'Доклад на конференции'),
        (13, 'Preprint', 'Препринт'),
        (14, 'Thesis', 'Дипломная работа'),
        (15, 'Dissertation', 'Диссертация'),
        (16, 'Study Protocol', 'Протокол исследования'),
        (17, 'Erratum', 'Эрратум'),
        (18, 'Correction', 'Исправление'),
        (19, 'Popular Science Article', 'Научно-популярная статья'),
        (20, 'Explainer', 'Объяснение'),
        (21, 'Tutorial', 'Учебное руководство'),
        (22, 'Instruction', 'Инструкция'),
        (23, 'Guide', 'Руководство'),
        (24, 'Listicle', 'Статья-список'),
        (25, 'Deep Dive', 'Глубокий анализ'),
        (26, 'Overview', 'Обзор'),
        (27, 'News', 'Новость'),
        (28, 'Sketch', 'Очерк'),
        (29, 'Report', 'Отчёт'),
        (30, 'Interview', 'Интервью'),
        (31, 'Profile', 'Профиль'),
        (32, 'Investigation', 'Расследование'),
        (33, 'Column', 'Колонка'),
        (34, 'Op-Ed', 'Авторская колонка'),
        (35, 'Essay', 'Эссе'),
        (36, 'Longread', 'Лонгрид'),
        (37, 'Photo Essay', 'Фотоочерк'),
        (38, 'Book Review', 'Рецензия на книгу'),
        (39, 'Critique', 'Критика'),
        (40, 'Literary Analysis', 'Литературный анализ'),
        (41, 'Abstract', 'Аннотация'),
        (42, 'Summary', 'Краткое содержание'),
        (43, 'Retelling', 'Пересказ'),
        (44, 'Reading Notes', 'Заметки о прочитанном'),
        (45, 'Recommendation', 'Рекомендация'),
        (46, 'Comparison', 'Сравнение'),
        (47, 'Author Profile', 'Профиль автора'),
        (48, 'Post', 'Пост'),
        (49, 'Note', 'Заметка'),
        (50, 'Thought', 'Мысль'),
        (51, 'Story', 'История'),
        (52, 'Thread', 'Тред'),
        (53, 'Diary', 'Дневник'),
        (54, 'Rant', 'Рант'),
        (55, 'Meme Post', 'Пост-мем'),
        (56, 'White Paper', 'Белая книга'),
        (57, 'Documentation', 'Документация'),
        (58, 'Changelog', 'Журнал изменений'),
        (59, 'Release Notes', 'Примечания к выпуску'),
        (60, 'Specification', 'Спецификация'),
        (61, 'Benchmark', 'Бенчмарк'),
        (62, 'Technical Review', 'Технический обзор'),
        (63, 'Clinical Case', 'Клинический случай'),
        (64, 'Postmortem', 'Постмортем'),
        (65, 'Fiction', 'Художественная литература'),
        (66, 'Short Story', 'Рассказ'),
        (67, 'Poem', 'Стихотворение'),
        (68, 'Novella', 'Повесть'),
        (69, 'Flash Fiction', 'Флеш-фикшн'),
        (70, 'Fanfic', 'Фанфик'),
        (71, 'Publicism', 'Публицистика'),
        (72, 'Pamphlet', 'Памфлет'),
        (73, 'Feuilleton', 'Фельетон'),
        (74, 'Vignette', 'Зарисовка'),
        (75, 'Reportage', 'Репортаж'),
        (76, 'Press Note', 'Пресс-заметка'),
        (77, 'Announcement', 'Анонс'),
        (78, 'Press Release', 'Пресс-релиз'),
        (79, 'Biographical Article', 'Биографическая статья'),
        (80, 'Memoir', 'Мемуары'),
        (81, 'Travel Notes', 'Путевые заметки'),
        (82, 'Culinary Article', 'Кулинарная статья'),
        (83, 'Product Review', 'Обзор продукта'),
        (84, 'Comparative Review', 'Сравнительный обзор'),
        (85, 'Rating', 'Рейтинг'),
        (86, 'Top List', 'Топ-список'),
        (87, 'FAQ', 'Часто задаваемые вопросы'),
        (88, 'Dictionary Entry', 'Словарная статья'),
        (89, 'Encyclopedia Article', 'Энциклопедическая статья'),
        (90, 'Educational Article', 'Обучающая статья'),
        (91, 'Methodological Article', 'Методическая статья'),
        (92, 'Monograph', 'Монография'),
        (93, 'Précis', 'Реферат'),
        (94, 'Poster', 'Постер'),
        (95, 'Demo', 'Демонстрация'),
        (96, 'Lecture', 'Лекция'),
        (97, 'Synopsis', 'Синопсис'),
        (98, 'Coursework', 'Курсовая работа'),
        (99, 'Research Report', 'Отчёт об исследовании'),
        (100, 'Analytical Note', 'Аналитическая записка'),
        (101, 'Forecast', 'Прогноз'),
        (102, 'Chronicle', 'Хроника'),
        (103, 'Event Overview', 'Обзор событий'),
        (104, 'Advocacy Article', 'Агитационная статья'),
        (105, 'Sermon', 'Проповедь'),
        (106, 'Treatise', 'Трактат'),
        (107, 'Manifesto', 'Манифест'),
        (108, 'Declaration', 'Декларация'),
        (109, 'Open Letter', 'Открытое письмо'),
        (110, 'Marginalia', 'Маргиналии'),
        (111, 'Reading Diary', 'Дневник чтения'),
        (112, 'Quote Collection', 'Сборник цитат'),
        (113, 'Book Summary', 'Краткое содержание книги'),
        (114, 'Digest', 'Дайджест')
), missing AS (
    SELECT o.* FROM ordered o
    WHERE NOT EXISTS (
        SELECT 1 FROM types_translations tt
        WHERE tt.code = 'en' AND tt.title = o.title_en
    )
), inserted AS (
    INSERT INTO types (id)
    SELECT nextval(pg_get_serial_sequence('types', 'id'))
    FROM missing
    ORDER BY seq
    RETURNING id
), numbered AS (
    SELECT row_number() OVER () AS rn, id FROM inserted
), rows AS (
    SELECT row_number() OVER (ORDER BY seq) AS rn, title_en, title_ru FROM missing
)
INSERT INTO types_translations (type_id, code, title)
SELECT n.id, l.code, l.title
FROM numbered n
JOIN rows r ON r.rn = n.rn
CROSS JOIN LATERAL (VALUES ('en', r.title_en), ('ru', r.title_ru)) AS l(code, title);