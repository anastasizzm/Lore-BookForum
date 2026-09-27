-- migrate:up
ALTER TABLE profiles
DROP COLUMN icon_id,
ADD COLUMN avatar varchar(50) NOT NULL DEFAULT 'default';

-- migrate:down
ALTER TABLE profiles
DROP COLUMN avatar,
ADD COLUMN icon_id UUID NOT NULL REFERENCES files (id) ON DELETE RESTRICT ON UPDATE CASCADE,
CREATE INDEX idx_profiles_icon_id ON profiles (icon_id);
