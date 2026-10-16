-- migrate:up
CREATE TYPE publication_status AS ENUM ('public', 'draft');

ALTER TABLE publications 
    ADD COLUMN status publication_status NOT NULL DEFAULT 'draft';

-- migrate:down
ALTER TABLE publications
    DROP COLUMN status;

DROP TYPE publication_status
