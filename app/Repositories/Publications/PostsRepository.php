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

    public function getList(
        int $page, 
        int $pageSize, 
        string $search,
        array $includeObjects,
        ?int $parentId = null, 
        ?int $publicationId = null, 
        ?int $creatorId = null) : array
    {
        $whereClauses = ['c.is_active'];
        $params = [];
        if (!empty($search)){
            $whereClauses = 'u.username ILIKE :q';
            $params[':q'] = $search . '%';
        }
        if ($parentId !== null){
            $whereClauses[] = 'c.parent_id = :parentId';
            $params[':parentId'] = $parentId;
        }
        else $whereClauses[] = 'c.parent_id IS NULL';

        if ($creatorId !== null){
            $whereClauses[] = 'c.creator_id = :creatorId';
            $params[':creatorId'] = $creatorId;
        }

        if ($publicationId !== null){
            $whereClauses[] = 'c.publication_id = :publicationId';
            $params[':publicationId'] = $publicationId; 
        }

        $joinClauses = [];
        $selectClauses = [];
        foreach($includeObjects as $prop){
            switch($prop){
                case 'creator':
                    $joinClauses[] = 'INNER JOIN profiles prof ON prof.user_id = u.id';
                    $selectClauses[] = "u.id as u_id,\nu.username as u_username,\nprof.name as u_name,\nprof.surname as u_surname,\nprof.avatar as u_avatar";
                    break;
                
                case 'publication':
                    $joinClauses[] = 'INNER JOIN publications p ON c.publication_id = p.id';
                    $selectClauses[] = "p.id as pub_id,\np.title as pub_title,\np.icon_id as pub_icon_id,\np.created_at as pub_created_at";
                    break;
            }
        }

        $where = implode(" AND\n", $whereClauses);
        if (!empty($where)) $where = 'WHERE ' . $where;

        $joins = implode("\n", $joinClauses);
        $select = "SELECT c.id,\nc.content,\nc.is_active,\nc.creator_id,\nc.publication_id,\nc.created_at,\nc.likes_count,\nc.comments_count";
        if (!empty($selectClauses))
            $select = $select . ",\n" . implode(",\n", $selectClauses);

        $sql = "
            $select
            FROM comments c
            INNER JOIN users u ON u.id = c.creator_id
            $joins
            $where
            ORDER BY c.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo()->prepare($sql);
        foreach($params as $key => $value){
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize + 1, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (($page - 1) * $pageSize), PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(
            static fn(array $row) => Post::fromRow($row),
            $stmt->fetchAll(),
        );

        return $items;
    }

    public function setLike(
        int $commentId,
        int $userId
    ) : void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO comments_likes (user_id, comment_id)
            VALUES (:userId, :commentId)
            RETURNING comment_id'
        );
        $stmt->execute([':userId' => $userId, ':commentId' => $commentId]);
    }

    public function removeLike(
        int $commentId,
        int $userId
    ) : void
    {
        $stmt = $this->pdo()->prepare(
            'DELETE FROM comments_likes WHERE user_id = :userId AND comment_id = :commentId'
        );
        $stmt->execute([':userId' => $userId, ':commentId' => $commentId]);
    }

    public function addComment(int $creatorId, int $publicationId, string $content, ?int $parentId = null) : ?int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO comments (creator_id, publication_id, content, parent_id)
            VALUES (:creatorId, :publicationId, :content, :parentId)
            RETURNING id'
        );

        $stmt->execute([':creatorId' => $creatorId, ':publicationId' => $publicationId, ':content' => $content, ':parentId' => $parentId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }   
    
    public function removeComment(int $commentId) : void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE comments SET is_active = false WHERE id = :commentId'
        );
        $stmt->execute([':commentId' => $commentId]);
    }

    public function hasAccess(int $userId, int $commentId) : bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id
            FROM comments 
            WHERE id = :commentId AND creator_id = :userId'
        );
        $stmt->execute([':commentId' => $commentId, ':userId' => $userId]);

        return $stmt->fetchColumn() !== false;
    }
}