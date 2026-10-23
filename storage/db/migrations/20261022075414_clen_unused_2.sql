-- migrate:up
DROP TABLE user_pub_roles;
DROP TABLE user_system_roles;
DROP TABLE system_roles_permissions;
DROP TABLE pub_roles_permissions;

-- migrate:down

