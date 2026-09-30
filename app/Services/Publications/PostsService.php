<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\PostsRepository;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;

use App\Extensions\PdoExtensions;

use App\Models\Posts\Post;

use App\Models\PaginatedList;

final class PostsService
{
    public function __construct(
        private readonly PostsRepository $postsRepo
    ){}

    public function getList(
        PaginationQuery $pageQ,
        string $search,
        PropertiesQuery $props,
        ?int $parentId = null,
        ?int $publicationId = null,
        ?int $creatorId = null
    ) : PaginatedList
    {
        $errors = [];
        $isValid = $props->validateForType(Post::class, $errors);
        if(!$isValid) throw new ValidationException($errors);

        $page = $pageQ->page();
        $pageSize = $pageQ->pageSize();

        $items = $this->postsRepo->getList(
            $page, 
            $pageSize, 
            $search, 
            $props->getProps(),
            $parentId, 
            $publicationId,
            $creatorId
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function setLike(
        int $postId,
        int $userId
    ) : void
    {
        try{
            $this->postsRepo->setLike($postId, $userId);
        }
        catch(\PDOException $e){
            throw $this->translatePdoException($e);
        }
    }

    public function removeLike(
        int $postId,
        int $userId
    ) : void
    {
        try{
            $this->postsRepo->removeLike($postId, $userId);
        }
        catch(\PDOException $e){
            throw $this->translatePdoException($e);
        }
    }

    private const FK_CONSTRAINTS = [
        'comments_likes_comment_id_fkey' => 'user_id',
        'comments_likes_user_id_fkey' =>'comment_id',
    ];

    private const FK_MESSAGES = [
        'user_id' => 'The user does not exist',
        'comment_id' => 'The comment does not exist',
    ];

    /** @throws ValidationException */
    private function translatePdoException(\PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());
        if ($constraint === null) {
            return $e;
        }

        if ($e->getCode() == '23503'){
            $field = self::FK_CONSTRAINTS[$constraint] ?? null;
            if ($field === null)
                return $e;

            return new ValidationException([
                $field => [self::FK_MESSAGES[$field]],
            ]);
        }

        if ($e->getCode() == '23505'){
            return new ValidationException(['comment_id' => ["The user already set like to this comment"]]);
        }

        return $e;
    }
}