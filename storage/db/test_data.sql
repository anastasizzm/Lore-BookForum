-- =====================================================================
-- test_data.sql: 3 random books + 3 random articles, created by the admin
-- DEVELOPMENT ONLY. Run automatically by migrate.sh (once) when
-- SEED_TEST_DATA=true in .env.
--
-- Needs init.sql to have run (admin user with is_admin, genres, categories).
-- Every run generates different random data. Files are database rows only;
-- the object keys do not exist in your object storage.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Temporary helpers (dropped automatically when the session ends)
-- ---------------------------------------------------------------------
CREATE FUNCTION pg_temp.pick(arr anyarray) RETURNS anyelement
LANGUAGE sql AS $f$
    SELECT arr[1 + floor(random() * cardinality(arr))::int]
$f$;

CREATE FUNCTION pg_temp.rand(lo int, hi int) RETURNS int
LANGUAGE sql AS $f$
    SELECT lo + floor(random() * (hi - lo + 1))::int
$f$;

CREATE FUNCTION pg_temp.random_isbn() RETURNS text
LANGUAGE sql AS $f$
    SELECT format('%s-%s-%s-%s-%s',
        pg_temp.pick(ARRAY['978', '979']),
        pg_temp.rand(0, 9),
        lpad(pg_temp.rand(0, 999)::text, 3, '0'),
        lpad(pg_temp.rand(0, 99999)::text, 5, '0'),
        pg_temp.rand(0, 9))
$f$;

CREATE FUNCTION pg_temp.lorem(n int) RETURNS text
LANGUAGE sql AS $f$
    SELECT string_agg(pg_temp.pick(ARRAY[
        'The story begins on the eve of a long journey.',
        'Nobody in the village remembered who had built the old tower.',
        'A single letter changed everything for the small crew.',
        'They followed the river north until the maps ran out.',
        'Every rule had a price, and sooner or later someone paid it.',
        'The archive held more secrets than anyone dared to count.',
        'By morning the harbor was empty and the bells had stopped.',
        'It was a quiet argument, but it split the council in two.',
        'Memory, they learned, is a kind of map that keeps redrawing itself.',
        'The engine hummed softly while the stars slid past the window.',
        'Some doors only open for those who stop trying to force them.',
        'The evidence pointed somewhere nobody wanted to look.'
    ]), ' ')
    FROM generate_series(1, n)
$f$;


-- ---------------------------------------------------------------------
-- Random data
-- ---------------------------------------------------------------------
DO $seed$
DECLARE
    v_admin       bigint;
    v_genre_ids   int[];
    v_cat_ids     int[];
    v_adjectives  text[] := ARRAY['Silent','Crimson','Forgotten','Hollow','Golden','Distant',
                                  'Broken','Hidden','Endless','Winter','Iron','Pale'];
    v_nouns       text[] := ARRAY['Kingdom','Signal','Archive','Harbor','Compass','Garden',
                                  'Engine','Oath','River','Mirror','Tower','Orbit'];
    v_publishers  text[] := ARRAY['Lore Press','Northlight Books','Blue Heron',
                                  'Paper Lantern','Old Oak Publishing'];
    v_article_pre text[] := ARRAY['Notes on the ','Rethinking the ','A Short Guide to the ',
                                  'Understanding the ','In Defence of the '];

    v_book_ids    bigint[] := '{}';
    v_book_pages  int[]    := '{}';

    v_file_id     uuid;
    v_pub_id      bigint;
    v_book_id     bigint;
    v_pages       int;
    v_chapters    int;
    v_start       int;
    v_end         int;
    v_idx         int;
    v_doi         text;
    i             int;
BEGIN
    -- The first admin user created by init.sql
    SELECT u.id INTO v_admin
    FROM users u
    JOIN users_rules r ON r.user_id = u.id
    WHERE r.is_admin
    ORDER BY u.id
    LIMIT 1;

    IF v_admin IS NULL THEN
        RAISE EXCEPTION 'No admin user found (users_rules.is_admin). Run init.sql first.';
    END IF;

    SELECT array_agg(id) INTO v_genre_ids FROM genres;
    SELECT array_agg(id) INTO v_cat_ids   FROM categories;

    IF v_genre_ids IS NULL OR v_cat_ids IS NULL THEN
        RAISE EXCEPTION 'No genres/categories found. Run init.sql first.';
    END IF;

    -- ---------------- 3 books ----------------
    FOR i IN 1..3 LOOP
        v_pages := pg_temp.rand(120, 720);

        INSERT INTO files (object_key, content_type, size_bytes, uploaded_by)
        VALUES ('test-data/books/' || gen_random_uuid() || '.pdf',
                'application/pdf',
                pg_temp.rand(500000, 9000000),
                v_admin)
        RETURNING id INTO v_file_id;

        INSERT INTO publications (title, description, genre_id, author_notes, creator_id)
        VALUES ('The ' || pg_temp.pick(v_adjectives) || ' ' || pg_temp.pick(v_nouns),
                pg_temp.lorem(pg_temp.rand(2, 4)),
                pg_temp.pick(v_genre_ids),
                'Random test data.',
                v_admin)
        RETURNING id INTO v_pub_id;

        INSERT INTO books (publication_id, category_id, publisher, pages, isbn, content_id)
        VALUES (v_pub_id,
                pg_temp.pick(v_cat_ids),
                pg_temp.pick(v_publishers),
                v_pages,
                pg_temp.random_isbn(),
                v_file_id)
        RETURNING id INTO v_book_id;

        v_book_ids   := v_book_ids   || v_book_id;
        v_book_pages := v_book_pages || v_pages;

        v_chapters := pg_temp.rand(3, 8);
        INSERT INTO books_chapters (book_id, chapter_number, title, page_start)
        SELECT v_book_id,
               ch.n,
               'Chapter ' || ch.n || ': ' || pg_temp.pick(v_adjectives) || ' ' || pg_temp.pick(v_nouns),
               1 + ((ch.n - 1) * v_pages) / v_chapters
        FROM generate_series(1, v_chapters) AS ch(n);
    END LOOP;

    -- ---------------- 3 articles (content, book, content) ----------------
    FOR i IN 1..3 LOOP
        v_doi := CASE WHEN random() < 0.7
                      THEN '10.5555/' || pg_temp.rand(100000, 999999999)
                 END;

        INSERT INTO publications (title, description, genre_id, author_notes, creator_id)
        VALUES (pg_temp.pick(v_article_pre) || pg_temp.pick(v_adjectives) || ' ' || pg_temp.pick(v_nouns),
                pg_temp.lorem(pg_temp.rand(2, 3)),
                pg_temp.pick(v_genre_ids),
                'Random test data.',
                v_admin)
        RETURNING id INTO v_pub_id;

        IF i % 2 = 1 THEN
            -- standalone article with its own text
            INSERT INTO articles (publication_id, doi, type, content)
            VALUES (v_pub_id, v_doi, 'content',
                    pg_temp.lorem(pg_temp.rand(4, 6)) || E'\n\n' ||
                    pg_temp.lorem(pg_temp.rand(4, 6)) || E'\n\n' ||
                    pg_temp.lorem(pg_temp.rand(3, 5)));
        ELSE
            -- article taken from one of the random books above
            v_idx   := pg_temp.rand(1, cardinality(v_book_ids));
            v_start := pg_temp.rand(1, v_book_pages[v_idx] - 20);
            v_end   := LEAST(v_book_pages[v_idx], v_start + pg_temp.rand(5, 40));

            INSERT INTO articles (publication_id, doi, type, book_id, page_start, page_end)
            VALUES (v_pub_id, v_doi, 'book', v_book_ids[v_idx], v_start, v_end);
        END IF;
    END LOOP;

    RAISE NOTICE 'Test data: 3 books and 3 articles created by user id %', v_admin;
END
$seed$;