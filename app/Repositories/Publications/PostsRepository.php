<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\Publications\PostsScriptDirector;
use App\Lib\Data\Database;
use App\Models\Posts\Post;
use App\Models\Criterias\Publications\PostsCriteria;
use PDO;

final class PostsRepository extends Repository
{
    private readonly PostsScriptDirector $director;

    public function __construct(Database $db){
        parent::__construct($db);
        $director = new PostsScriptDirector();
    }

    public function getList(PostsCriteria $criteria, array $includeObjects = []) : array
    {
        $this->director->startTempFilter()->addPostSelectTemp();
        $filters = $criteria->filters;

        if (!empty($filters->search))
            $this->director->addSearchTempFilter($filters->search);

        $this->director->addParentTempFilter($filters->parentId);

        if ($creatorId !== null)
            $this->director->addCreatorTempFilter($filters->creatorId);

        if ($publicationId !== null)
            $this->director->addPublicationTempFilter($filters->publicationId);

        $this->director->addIncludesTemp($includeObjects);
        $this->director->addOrderTemp($criteria->sortBy)
            ->setExtraPaginationTemp($criteria->page, $criteria->pageSize);

        $data = $this->director->buildTempFilter();
        $stmt = $this->executeScript($data);

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