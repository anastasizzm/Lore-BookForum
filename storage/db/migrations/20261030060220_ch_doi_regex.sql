-- migrate:up
ALTER TABLE articles
    DROP CONSTRAINT articles_doi_check;

ALTER TABLE articles 
    ADD CHECK (doi ~ '^10.\d{4,9}\/[-._;()\/:A-Z0-9]+$');

-- migrate:down
ALTER TABLE articles
    DROP CONSTRAINT articles_doi_check;

ALTER TABLE articles 
    ADD CHECK (doi ~ '^10\.\d+\/\d+$');

