-- migrate:up
ALTER TABLE publications
ADD COLUMN rating_sum BIGINT NOT NULL DEFAULT 0,
ADD COLUMN rating_count BIGINT NOT NULL DEFAULT 0,
ADD COLUMN rating_avg INT GENERATED ALWAYS AS (
    COALESCE(ROUND(rating_sum::numeric * 10 / NULLIF(rating_count, 0))::int, 0)
) STORED;

CREATE OR REPLACE FUNCTION bump_publication_rating() RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE publications
        SET rating_count = rating_count + 1,
            rating_sum   = rating_sum + NEW.rating
        WHERE id = NEW.publication_id;
        RETURN NULL;
    END IF;

    IF TG_OP = 'DELETE' THEN
        UPDATE publications
        SET rating_count = rating_count - 1,
            rating_sum   = rating_sum - OLD.rating
        WHERE id = OLD.publication_id;
        RETURN NULL;
    END IF;

    IF TG_OP = 'UPDATE' THEN
        IF NEW.publication_id <> OLD.publication_id THEN
            UPDATE publications
            SET rating_count = rating_count - 1,
                rating_sum   = rating_sum - OLD.rating
            WHERE id = OLD.publication_id;

            UPDATE publications
            SET rating_count = rating_count + 1,
                rating_sum   = rating_sum + NEW.rating
            WHERE id = NEW.publication_id;
        ELSIF NEW.rating <> OLD.rating THEN
            UPDATE publications
            SET rating_sum = rating_sum - OLD.rating + NEW.rating
            WHERE id = NEW.publication_id;
        END IF;

        RETURN NULL;
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER ratings_bump
AFTER INSERT OR DELETE OR UPDATE OF rating, publication_id ON ratings
FOR EACH ROW EXECUTE FUNCTION bump_publication_rating();

-- migrate:down
ALTER TABLE publications
DROP COLUMN rating_sum,
DROP COLUMN rating_count
DROP COLUMN rating_avg;

DROP TRIGGER IF EXISTS ratings_bump ON ratings;
DROP FUNCTION IF EXISTS bump_publication_rating();
