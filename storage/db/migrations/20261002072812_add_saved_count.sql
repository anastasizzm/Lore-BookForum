-- migrate:up
ALTER TABLE publications
  ADD COLUMN saved_count INTEGER NOT NULL DEFAULT 0;

CREATE OR REPLACE FUNCTION update_publication_saved_count()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  IF TG_OP = 'INSERT' THEN
    UPDATE publications
       SET saved_count = saved_count + 1
     WHERE id = NEW.publication_id;
    RETURN NEW;

  ELSIF TG_OP = 'DELETE' THEN
    UPDATE publications
       SET saved_count = saved_count - 1
     WHERE id = OLD.publication_id;
    RETURN OLD;

  ELSIF TG_OP = 'UPDATE' THEN
    -- на случай, если поменяли publication_id
    IF NEW.publication_id <> OLD.publication_id THEN
      UPDATE publications
         SET saved_count = saved_count - 1
       WHERE id = OLD.publication_id;

      UPDATE publications
         SET saved_count = saved_count + 1
       WHERE id = NEW.publication_id;
    END IF;
    RETURN NEW;
  END IF;

  RETURN NULL;
END;
$$;

CREATE TRIGGER trg_saved_publications_counter
AFTER INSERT OR UPDATE OR DELETE
ON saved_publications
FOR EACH ROW
EXECUTE FUNCTION update_publication_saved_count();

-- migrate:down
DROP TRIGGER IF EXISTS trg_saved_publications_counter ON saved_publications;
DROP FUNCTION IF EXISTS update_publication_saved_count();

ALTER TABLE publications
DROP COLUMN saved_count;
