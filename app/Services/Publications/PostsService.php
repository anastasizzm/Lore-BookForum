<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\PostsRepository;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;

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
}