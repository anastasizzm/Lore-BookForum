<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Repository;
use App\Lib\Data\Database;
use App\Models\UserContext\PostContext;

final class PostsContextRepository extends Repository
{
    public function __construct(
        Database $db
    ) {
        parent::__construct($db);
    }

    /**
     * @param int[] $postIds
     * @return array<int, PostContext>
     */
    public function loadMap(int $viewerId, array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));

        $sql = "
            WITH viewer AS (
                SELECT u.user_id, bool_or(ur.id IS NOT NULL AND ur.is_admin) as is_admin FROM (SELECT ?::bigint AS user_id) u
                LEFT JOIN users_rules ur ON u.user_id = ur.user_id
                GROUP BY u.user_id
            )

            SELECT
                p.id AS post_id,
                COALESCE(bool_or(likes.comment_id IS NOT NULL), false) as is_liked,
                COALESCE(bool_or(v.is_admin OR p.creator_id = v.user_id), false) as is_editor
            FROM comments p
            CROSS JOIN viewer v
            LEFT JOIN comments_likes likes ON likes.comment_id = p.id AND likes.user_id = v.user_id
            WHERE p.id IN ($placeholders)
            GROUP BY p.id, v.user_id
        ";

        $params = array_merge([$viewerId], $postIds);
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['post_id']] = PostContext::fromRow($row);
        }

        return $map;
    }
}