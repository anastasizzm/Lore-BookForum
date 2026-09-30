-- migrate:up
CREATE OR REPLACE FUNCTION bump_post_likes_count() RETURNS TRIGGER AS 
$$ 
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE comments SET likes_count = likes_count + 1 WHERE id = NEW.comment_id;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE comments SET likes_count = likes_count - 1 WHERE id = OLD.comment_id;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER likes_bump
AFTER INSERT OR DELETE ON comments_likes
FOR EACH ROW EXECUTE FUNCTION bump_post_likes_count();

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
DROP TRIGGER IF EXISTS likes_bump    ON comments_likes;
DROP TRIGGER IF EXISTS comments_bump ON comments;
DROP FUNCTION IF EXISTS bump_post_likes_count();
DROP FUNCTION IF EXISTS bump_comments_counters();
