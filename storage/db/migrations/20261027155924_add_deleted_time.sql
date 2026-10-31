-- migrate:up
ALTER TABLE user_roles
    ADD COLUMN deleted_at TIMESTAMPTZ,
    ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (deleted_at IS NULL) STORED;

ALTER TABLE publications
    ADD COLUMN deleted_at TIMESTAMPTZ,
    ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (deleted_at IS NULL) STORED;

DROP TRIGGER IF EXISTS comments_bump ON comments;
DROP FUNCTION IF EXISTS bump_comments_counters();

ALTER TABLE comments
    ADD COLUMN deleted_at TIMESTAMPTZ,
    DROP COLUMN is_active;

ALTER TABLE comments
    ADD COLUMN is_active BOOLEAN GENERATED ALWAYS AS (deleted_at IS NULL) STORED;

CREATE OR REPLACE FUNCTION bump_comments_counters() RETURNS TRIGGER AS $$
DECLARE
    delta INT;
    pub_id BIGINT;
    par_id BIGINT;
BEGIN
    -- Определяем, что менять
    IF TG_OP = 'INSERT' THEN
        IF NOT NEW.is_active THEN
            RETURN NULL;   -- неактивный комментарий ничего не считает
        END IF;
        delta  := 1;
        pub_id := NEW.publication_id;
        par_id := NEW.parent_id;

    ELSIF TG_OP = 'DELETE' THEN
        IF NOT OLD.is_active THEN
            RETURN NULL;
        END IF;
        delta  := -1;
        pub_id := OLD.publication_id;
        par_id := OLD.parent_id;

    ELSIF TG_OP = 'UPDATE' THEN
        -- Реагируем только на смену is_active
        IF NEW.is_active = OLD.is_active THEN
            RETURN NULL;
        END IF;
        delta  := CASE WHEN NEW.is_active THEN 1 ELSE -1 END;
        pub_id := NEW.publication_id;
        par_id := NEW.parent_id;

    ELSE
        RETURN NULL;
    END IF;

    -- 1. Всегда обновляем счётчик у публикации
    IF pub_id IS NOT NULL THEN
        UPDATE publications
        SET comments_count = comments_count + delta
        WHERE id = pub_id;
    END IF;

    -- 2. Если есть родитель — обновляем и его comments_count
    IF par_id IS NOT NULL THEN
        UPDATE comments
        SET comments_count = comments_count + delta
        WHERE id = par_id;
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER comments_bump
AFTER INSERT OR DELETE OR UPDATE OF is_active ON comments
FOR EACH ROW EXECUTE FUNCTION bump_comments_counters();

-- migrate:down
ALTER TABLE user_roles
    DROP COLUMN is_active,
    DROP COLUMN deleted_at;

ALTER TABLE publications
    DROP COLUMN is_active,
    DROP COLUMN deleted_at;

DROP TRIGGER IF EXISTS comments_bump ON comments;
DROP FUNCTION IF EXISTS bump_comments_counters();

ALTER TABLE comments
    DROP COLUMN is_active,
    DROP COLUMN deleted_at;

ALTER TABLE comments
    ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE;

CREATE OR REPLACE FUNCTION bump_comments_counters() RETURNS TRIGGER AS $$
DECLARE
    delta INT;
    pub_id BIGINT;
    par_id BIGINT;
BEGIN
    -- Определяем, что менять
    IF TG_OP = 'INSERT' THEN
        IF NOT NEW.is_active THEN
            RETURN NULL;   -- неактивный комментарий ничего не считает
        END IF;
        delta  := 1;
        pub_id := NEW.publication_id;
        par_id := NEW.parent_id;

    ELSIF TG_OP = 'DELETE' THEN
        IF NOT OLD.is_active THEN
            RETURN NULL;
        END IF;
        delta  := -1;
        pub_id := OLD.publication_id;
        par_id := OLD.parent_id;

    ELSIF TG_OP = 'UPDATE' THEN
        -- Реагируем только на смену is_active
        IF NEW.is_active = OLD.is_active THEN
            RETURN NULL;
        END IF;
        delta  := CASE WHEN NEW.is_active THEN 1 ELSE -1 END;
        pub_id := NEW.publication_id;
        par_id := NEW.parent_id;

    ELSE
        RETURN NULL;
    END IF;

    -- 1. Всегда обновляем счётчик у публикации
    IF pub_id IS NOT NULL THEN
        UPDATE publications
        SET comments_count = comments_count + delta
        WHERE id = pub_id;
    END IF;

    -- 2. Если есть родитель — обновляем и его comments_count
    IF par_id IS NOT NULL THEN
        UPDATE comments
        SET comments_count = comments_count + delta
        WHERE id = par_id;
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER comments_bump
AFTER INSERT OR DELETE OR UPDATE OF is_active ON comments
FOR EACH ROW EXECUTE FUNCTION bump_comments_counters();
