<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\PostsRepository;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;

use App\Forms\Publications\PostForm;

use App\Extensions\PdoExtensions;

use App\Models\Posts\Post;

use App\Models\PaginatedList;

use Throwable;
use App\Exceptions\ValidationException;
use App\Exceptions\PostExceptionTranslator;

final class PostsService
{
    public function __construct(
        private readonly PostsRepository $postsRepo,
        private readonly PostExceptionTranslator $translator
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
           throw $this->translator->translate($e);
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
           throw $this->translator->translate($e);
        }
    }
    
    public function addComment(int $creatorId, PostForm $form, ?int $parentId = NULL) : int
    {
        $errors = [];
        if (!$form->validate($errors)) throw new ValidationException($errors);

        try{
            return $this->postsRepo->addComment($creatorId, $form->publicationId, $form->content, $parentId);
        }
        catch(\PDOException $e){
            throw $this->translator->translate($e);
        }
    }

    public function removeComment(int $commentId) : void
    {
        try{
            $this->postsRepo->removeComment($commentId);
        }
        catch(\PDOException $e){
           throw $this->translator->translate($e);
        }
    } 

    public function hasAccess(int $userId, int $postId) : bool
    {
        return $this->postsRepo->hasAccess($userId, $postId);
    }
}