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
        $this->director = new PostsScriptDirector();
    }

    public function getList(PostsCriteria $criteria, array $includeObjects = []) : array
    {
        $this->director->startTempFilter()
            ->addPostSelectTemp()
            ->addActiveTempFilter();
            
        $filters = $criteria->filters;

        if (!empty($filters->search))
            $this->director->addSearchTempFilter($filters->search);

        $this->director->addParentTempFilter($filters->parentId);

        if ($filters->creatorId !== null)
            $this->director->addCreatorTempFilter($filters->creatorId);

        if ($filters->publicationId !== null)
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
            SELECT :userId::bigint, id FROM comments
            WHERE id = :commentId and is_active
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
            'DELETE FROM comments_likes 
            WHERE user_id = :userId 
            AND comment_id = :commentIdOuter
            AND EXISTS (SELECT 1 FROM comments WHERE id = :commentIdInner AND is_active)'
        );
        $stmt->execute([':userId' => $userId, ':commentIdOuter' => $commentId, ':commentIdInner' => $commentId]);
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
            'UPDATE comments SET deleted_at = NOW() WHERE id = :commentId AND is_active'
        );
        $stmt->execute([':commentId' => $commentId]);
    }
}