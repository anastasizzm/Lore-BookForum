<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\PublicationsRepository;
use App\Queries\PaginationQuery;
use App\Queries\PropertiesQuery;
use App\Queries\SortQuery;

use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Enums\PublicationsSortBy;

final class PublicationsService
{
    public function __construct(
        private readonly PublicationsRepository $pubsRepo
    ){}

    public function getList(
        PaginationQuery $pageQ,
        string $search,
        PropertiesQuery $props,
        SortQuery $sort,
        ?int $genreId = null,
        ?int $creatorId = null
    ) : PaginatedList
    {
        $errors = [];
        $isValid = $props->validateForType(Publication::class, $errors);
        if(!$isValid) throw new ValidationException($errors);
        
        $sortEnum = $sort->tryResolve(PublicationsSortBy::class) ?? PublicationsSortBy::Newest;
        $page = $pageQ->page();
        $pageSize = $pageQ->pageSize();

        $items = $this->pubsRepo->getList(
            $page, 
            $pageSize, 
            $search, 
            $sortEnum,
            $props->getProps(),
            $genreId, 
            $creatorId
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }
}