-- migrate:up
ALTER TABLE pub_roles_permissions RENAME COLUMN can_create_pubs TO pubs_create;
ALTER TABLE pub_roles_permissions RENAME COLUMN can_edit_pubs TO pubs_update;
ALTER TABLE pub_roles_permissions RENAME COLUMN can_delete_pubs TO pubs_delete;
ALTER TABLE pub_roles_permissions RENAME COLUMN can_manage_roles TO pubs_manage_roles;

ALTER TABLE system_roles_permissions RENAME COLUMN can_create_pubs TO pubs_create;
ALTER TABLE system_roles_permissions RENAME COLUMN can_edit_pubs TO pubs_update;
ALTER TABLE system_roles_permissions RENAME COLUMN can_delete_pubs TO pubs_delete;
ALTER TABLE system_roles_permissions RENAME COLUMN can_manage_roles TO pubs_manage_roles;
ALTER TABLE system_roles_permissions RENAME COLUMN can_control_users TO sys_control_users;
ALTER TABLE system_roles_permissions RENAME COLUMN can_control_additionals TO sys_control_additionals;
ALTER TABLE system_roles_permissions ADD COLUMN sys_manage_roles BOOLEAN NOT NULL DEFAULT FALSE;

-- migrate:down
ALTER TABLE pub_roles_permissions RENAME COLUMN pubs_create TO can_create_pubs;
ALTER TABLE pub_roles_permissions RENAME COLUMN pubs_update TO can_edit_pubs;
ALTER TABLE pub_roles_permissions RENAME COLUMN pubs_delete TO can_delete_pubs;
ALTER TABLE pub_roles_permissions RENAME COLUMN pubs_manage_roles TO can_manage_roles;

ALTER TABLE system_roles_permissions RENAME COLUMN pubs_create TO can_create_pubs;
ALTER TABLE system_roles_permissions RENAME COLUMN pubs_update TO can_edit_pubs;
ALTER TABLE system_roles_permissions RENAME COLUMN pubs_delete TO can_delete_pubs;
ALTER TABLE system_roles_permissions RENAME COLUMN pubs_manage_roles TO can_manage_roles;
ALTER TABLE system_roles_permissions RENAME COLUMN sys_control_users TO can_control_users;
ALTER TABLE system_roles_permissions RENAME COLUMN sys_control_additionals TO can_control_additionals;
ALTER TABLE system_roles_permissions DROP COLUMN sys_manage_roles;
