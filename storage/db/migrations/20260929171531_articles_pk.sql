-- migrate:up
ALTER TABLE articles
    ADD CONSTRAINT articles_publication_id_unique UNIQUE (publication_id);

ALTER TABLE articles
    DROP CONSTRAINT articles_pkey;

ALTER TABLE articles
    ALTER COLUMN publication_id SET NOT NULL;
ALTER TABLE articles
    ADD CONSTRAINT articles_pkey PRIMARY KEY (publication_id);

ALTER TABLE articles
    DROP CONSTRAINT articles_publication_id_unique;

ALTER TABLE articles
    DROP COLUMN id;

-- migrate:down
ALTER TABLE articles
    DROP CONSTRAINT articles_pkey;

ALTER TABLE articles
    ADD COLUMN id BIGSERIAL;
UPDATE articles SET id = publication_id;
ALTER TABLE articles
    ADD CONSTRAINT articles_pkey PRIMARY KEY (id);