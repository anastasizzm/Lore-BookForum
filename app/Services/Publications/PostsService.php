<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\PostsRepository;
use App\Forms\Queries\PaginationQuery;
use App\Forms\Queries\PropertiesQuery;

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
        $isValid = true;
        foreach([$pageQ, $props] as $form){
            $isValid = $isValid & $form->validate($errors);
        }

        if(!$isValid) throw new ValidationException($errors);

        $page = $pageQ->page;
        $pageSize = $pageQ->pageSize;

        $items = $postsRepo->getList(
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