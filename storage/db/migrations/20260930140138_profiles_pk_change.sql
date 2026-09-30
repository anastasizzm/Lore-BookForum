-- migrate:up
ALTER TABLE profiles DROP CONSTRAINT profiles_pkey;
ALTER TABLE profiles ADD CONSTRAINT profiles_pkey PRIMARY KEY (user_id);
ALTER TABLE profiles DROP COLUMN id;

-- migrate:down
ALTER TABLE profiles DROP CONSTRAINT profiles_pkey;
ALTER TABLE profiles ADD COLUMN id BIGSERIAL;
UPDATE profiles SET id = user_id;
ALTER TABLE profiles ADD CONSTRAINT profiles_pkey PRIMARY KEY (id);