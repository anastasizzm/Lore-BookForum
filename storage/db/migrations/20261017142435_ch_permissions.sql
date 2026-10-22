-- migrate:up
CREATE TABLE IF NOT EXISTS roles_permissions(
    role_id INT NOT NULL REFERENCES user_roles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    publication_id BIGINT DEFAULT NULL REFERENCES publications(id) ON DELETE CASCADE ON UPDATE CASCADE,
    pub_create BOOLEAN NOT NULL DEFAULT FALSE,
    pub_update_meta BOOLEAN NOT NULL DEFAULT FALSE,
    pub_update_content BOOLEAN NOT NULL DEFAULT FALSE,
    pub_delete BOOLEAN NOT NULL DEFAULT FALSE,
    pub_read BOOLEAN NOT NULL DEFAULT FALSE,
    pub_manage_ppl BOOLEAN NOT NULL DEFAULT FALSE,
    pub_manage_roles BOOLEAN NOT NULL DEFAULT FALSE,
    post_create BOOLEAN NOT NULL DEFAULT FALSE,
    post_update BOOLEAN NOT NULL DEFAULT FALSE,
    post_delete BOOLEAN NOT NULL DEFAULT FALSE,
    sys_manage_accounts BOOLEAN NOT NULL DEFAULT FALSE,
    sys_manage_additionals BOOLEAN NOT NULL DEFAULT FALSE,
    sys_manage_ppl BOOLEAN NOT NULL DEFAULT FALSE,
    sys_manage_roles BOOLEAN NOT NULL DEFAULT FALSE,
    authority_rank INT NOT NULL DEFAULT 0,

    CONSTRAINT roles_permissions_pub_role_uq
        UNIQUE NULLS NOT DISTINCT (publication_id, role_id),

    CONSTRAINT roles_permissions_pub_rank_uq
        UNIQUE NULLS NOT DISTINCT (publication_id, authority_rank)
        DEFERRABLE INITIALLY DEFERRED
);

CREATE TABLE IF NOT EXISTS roles_users(
    id BIGSERIAL NOT NULL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    publication_id BIGINT DEFAULT NULL REFERENCES publications(id) ON DELETE CASCADE ON UPDATE CASCADE,
    role_id INT NOT NULL,
    valid_from TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    valid_to TIMESTAMPTZ NULL,
    is_valid BOOLEAN GENERATED ALWAYS AS (valid_to IS NULL) STORED,
    granted_by_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,

    FOREIGN KEY (publication_id, role_id)
        REFERENCES roles_permissions(publication_id, role_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    UNIQUE (user_id, publication_id, role_id, valid_from),
    UNIQUE NULLS NOT DISTINCT (user_id, publication_id, role_id, valid_from)
);
-- migrate:down
DROP TABLE IF EXISTS roles_users;
DROP TABLE IF EXISTS roles_permissions;
