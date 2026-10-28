-- migrate:up
CREATE INDEX idx_profiles_fullname_trgm_gin 
ON profiles 
USING gin ((COALESCE(name, '') || ' ' || COALESCE(surname, '')) gin_trgm_ops);

CREATE INDEX idx_users_username_trgm_gin
ON users
USING gin (username gin_trgm_ops);

-- migrate:down
DROP INDEX IF EXISTS idx_profiles_fullname_trgm_gin;
DROP INDEX IF EXISTS idx_users_username_trgm_gin;