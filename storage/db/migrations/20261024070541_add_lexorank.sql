-- migrate:up

-- 1. lexorank вместо authority_rank
ALTER TABLE roles_permissions
    ADD COLUMN IF NOT EXISTS lexorank VARCHAR(255);

WITH ordered AS (
    SELECT
        role_id,
        publication_id,
        lpad(
            (ROW_NUMBER() OVER (
                PARTITION BY publication_id
                ORDER BY authority_rank, role_id
            ) - 1)::text,
            10, '0'
        ) AS new_rank
    FROM roles_permissions
)
UPDATE roles_permissions rp
SET lexorank = o.new_rank
FROM ordered o
WHERE rp.role_id = o.role_id
  AND rp.publication_id IS NOT DISTINCT FROM o.publication_id;

ALTER TABLE roles_permissions
    ALTER COLUMN lexorank SET NOT NULL;

-- 2. Убрать старые constraints на authority_rank
ALTER TABLE roles_permissions
    DROP CONSTRAINT IF EXISTS roles_permissions_pub_rank_uq;

ALTER TABLE roles_permissions
    DROP COLUMN IF EXISTS authority_rank;

-- 3. Индекс для сортировки
CREATE UNIQUE INDEX IF NOT EXISTS roles_permissions_pub_lexorank_uq
    ON roles_permissions (publication_id, lexorank COLLATE "C")
    NULLS NOT DISTINCT;

-- migrate:down