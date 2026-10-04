<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\PostsRepository;

use App\Forms\Publications\PostForm;

use App\Extensions\PdoExtensions;

use App\Models\Criterias\Publications\PostsCriteria;
use App\Models\Queries\Publications\PostsListQuery;
use App\Models\Posts\Post;
use App\Models\Enums\PostsSortBy;
use App\Models\PaginatedList;

use Throwable;
use App\Exceptions\ValidationException;
use App\Exceptions\Translators\PostExceptionTranslator;

final class PostsService
{
    public function __construct(
        private readonly PostsRepository $postsRepo,
        private readonly PostExceptionTranslator $translator
    ){}

    public function getList(PostsListQuery $query) : PaginatedList
    {
        $errors = [];
        $isValid = $query->properties->validateForType(Post::class, $errors);
        if(!$isValid) throw new ValidationException($errors);

        $sortEnum = $query->sort->hasData() 
            ? EnumExtensions::tryResolve(PostsSortBy::class, $query->sort->sortString()) 
            : PostsSortBy::Newest;

        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();

        $items = $this->postsRepo->getList(
            new PostsCriteria(
                $page,
                $pageSize,
                $sortEnum,
                $query->filters
            ),
            $query->properties->getProps()
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