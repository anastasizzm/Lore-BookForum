-- migrate:up
ALTER TABLE pub_roles_permissions
    ADD COLUMN manage BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN read BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN manage_ppl BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN authority_rank INT NOT NULL,

    DROP COLUMN pubs_delete,
    DROP COLUMN pubs_create,
    DROP COLUMN pubs_update,
    DROP COLUMN pubs_manage_roles,

    ADD UNIQUE (publication_id, authority_rank) DEFERRABLE INITIALLY DEFERRED;

ALTER TABLE system_roles_permissions
    ADD COLUMN admin_posts BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN admin_publications BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN admin_users BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN admin_additionals BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN manage_ppl BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN authority_rank INT NOT NULL,

    DROP COLUMN pubs_delete,
    DROP COLUMN pubs_create,
    DROP COLUMN pubs_update,
    DROP COLUMN pubs_manage_roles,
    DROP COLUMN sys_manage_roles,
    DROP COLUMN sys_control_users,
    DROP COLUMN sys_control_additionals,

    ADD UNIQUE (authority_rank) DEFERRABLE INITIALLY DEFERRED;

-- migrate:down
ALTER TABLE pub_roles_permissions
  DROP CONSTRAINT IF EXISTS pub_roles_permissions_publication_id_authority_rank_key,
  
  ADD COLUMN pubs_delete BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN pubs_create BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN pubs_update BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN pubs_manage_roles BOOLEAN NOT NULL DEFAULT FALSE,
  
  DROP COLUMN manage,
  DROP COLUMN read,
  DROP COLUMN manage_ppl,
  DROP COLUMN authority_rank;


ALTER TABLE system_roles_permissions
  DROP CONSTRAINT IF EXISTS system_roles_permissions_authority_rank_key,
  
  ADD COLUMN pubs_delete BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN pubs_create BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN pubs_update BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN pubs_manage_roles BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN sys_manage_roles BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN sys_control_users BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN sys_control_additionals BOOLEAN NOT NULL DEFAULT FALSE,
  
  DROP COLUMN admin_posts,
  DROP COLUMN admin_publications,
  DROP COLUMN admin_users,
  DROP COLUMN admin_additionals,
  DROP COLUMN manage_ppl,
  DROP COLUMN authority_rank;

