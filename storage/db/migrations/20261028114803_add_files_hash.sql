-- migrate:up
ALTER TABLE files ADD COLUMN hash VARCHAR(64);

UPDATE files SET hash = id;
ALTER TABLE files ALTER COLUMN hash SET NOT NULL;

CREATE UNIQUE INDEX files_hash_uq ON files (hash);

-- migrate:down
DROP CONSTRAINT files_hash_uq ON files;
ALTER TABLE files DROP COLUMN hash;
