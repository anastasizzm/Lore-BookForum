<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Repository;

use PDO;
use App\Lib\Data\Database;

use App\Models\Posts\Post;

final class PostsRepository extends Repository
{
    public function __construct(Database $db){
        parent::__construct($db);
    }

    public function getTopList(int $page, int $pageSize, string $search) : array
    {
        $where = '';
        $params = [];
        if (!empty($search))
        {
            $where = 'WHERE (p.title ILIKE :q OR u.username ILIKE :q) AND p.is_active AND p.parent_id IS NULL';
            $params[':q'] = '%' . $search . '%';
        }

        $sql = "
            SELECT 
                c.id,
                c.content,
                c.is_active,
                c.creator_id,
                c.publication_id,
                c.created_at,
                u.id as u_id,
                u.username as u_username,
                prof.name as u_name,
                prof.surname as u_surname
                prof.avatar as u_avatar,
                p.id as pub_id,
                p.title as pub_title,
                p.icon_id as pub_icon_id,
                p.created_at as pub_created_at
            FROM comments c
            INNER JOIN publications p ON c.publication_id = p.id
            INNER JOIN users u ON u.id = c.creator_id
            INNER JOIN profiles prof ON prof.user_id = u.id
            $where
            ORDER BY c.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $pageSize + 1, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (($pageNum - 1) * $pageSize), PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(
            static fn(array $row) => Post::fromRow($row),
            $stmt->fetchAll(),
        );

        $hasNext = count($items) > $pageSize;
        if ($hasNext) {
            $items = array_slice($items, 0, $pageSize);
        }

        return [
            'items'   => $items,
            'total'   => $total,
            'page'    => $page,
            'pageSize' => $pageSize,
            'hasNext' => $hasNext
        ];
    }
}