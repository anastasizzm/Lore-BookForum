-- migrate:up
CREATE TABLE IF NOT EXISTS user_roles(
    id SERIAL NOT NULL PRIMARY KEY,
    color CHAR(6) NOT NULL DEFAULT 'B5B5B5',
    is_system BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE TABLE IF NOT EXISTS user_roles_translations(
    role_id INT NOT NULL REFERENCES user_roles(id) ON DELETE CASCADE ON UPDATE CASCADE,
    code CHAR(2) NOT NULL REFERENCES languages(code) ON DELETE RESTRICT ON UPDATE CASCADE,
    title VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS system_roles_permissions(
    role_id INT NOT NULL PRIMARY KEY REFERENCES user_roles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    can_control_users BOOLEAN NOT NULL DEFAULT FALSE,
    can_control_additionals BOOLEAN NOT NULL DEFAULT FALSE,
    can_create_pubs BOOLEAN NOT NULL DEFAULT FALSE,
    can_edit_pubs BOOLEAN NOT NULL DEFAULT FALSE,
    can_delete_pubs BOOLEAN NOT NULL DEFAULT FALSE,
    can_manage_roles BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE TABLE IF NOT EXISTS user_system_roles(
    id BIGSERIAL NOT NULL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    role_id INT NOT NULL REFERENCES system_roles_permissions(role_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    valid_from TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    valid_to TIMESTAMPTZ NULL,
    is_valid BOOLEAN GENERATED ALWAYS AS (valid_to IS NULL) STORED,
    granted_by_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,

    UNIQUE (user_id, role_id, valid_from)
);
CREATE INDEX idx_user_system_roles_user_role ON user_system_roles (user_id, role_id) WHERE valid_to IS NULL;

CREATE TABLE IF NOT EXISTS pub_roles_permissions(
    publication_id BIGINT NOT NULL REFERENCES publications(id) ON DELETE CASCADE ON UPDATE CASCADE,
    role_id INT NOT NULL REFERENCES user_roles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    can_create_pubs BOOLEAN NOT NULL DEFAULT FALSE,
    can_edit_pubs BOOLEAN NOT NULL DEFAULT FALSE,
    can_delete_pubs BOOLEAN NOT NULL DEFAULT FALSE,
    can_manage_roles BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (publication_id, role_id)
);

CREATE TABLE IF NOT EXISTS user_pub_roles(
    id BIGSERIAL NOT NULL PRIMARY KEY,
    publication_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    role_id INT NOT NULL,
    valid_from TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    valid_to TIMESTAMPTZ NULL,
    is_valid BOOLEAN GENERATED ALWAYS AS (valid_to IS NULL) STORED,
    granted_by_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,

    FOREIGN KEY (publication_id, role_id)
        REFERENCES pub_roles_permissions(publication_id, role_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    UNIQUE (publication_id, user_id, role_id, valid_from)
);

-- migrate:down
DROP TABLE user_roles;
DROP TABLE user_roles_translations;

DROP TABLE system_roles_permissions;
DROP TABLE user_system_roles;

DROP TABLE pub_roles_permissions;
DROP TABLE user_pub_roles;