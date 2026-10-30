-- migrate:up
ALTER TABLE files 
    DROP CONSTRAINT files_object_key_key;

-- migrate:down

