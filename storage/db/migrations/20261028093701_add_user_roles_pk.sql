-- migrate:up
ALTER TABLE user_roles_translations
    ADD PRIMARY KEY (role_id, code);

-- migrate:down

