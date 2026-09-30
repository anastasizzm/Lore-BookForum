<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\BooksRepository;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;

use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Filters\RadingStatusFilters;
use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ReadingStatus;

use App\Extensions\EnumExtensions;

final class BooksService
{
    public function __construct(
        private readonly BooksRepository $pubsRepo
    ){}

    public function getList(
        PaginationQuery $pageQ,
        string $search,
        PropertiesQuery $props,
        ?SortQuery $sort = null,
        ?StatusQuery $status = null,
        ?int $currentUserId = null,
        ?int $genreId = null,
        ?int $creatorId = null,
        ?string $isbn = null
    ) : PaginatedList
    {
        $errors = [];
        $isValid = $props->validateForType(Publication::class, $errors);
        if(!$isValid) throw new ValidationException($errors);
        
        $sortEnum = $sort == null ? null : EnumExtensions::tryResolve(PublicationsSortBy::class, $sort->sortString());
        $sortEnum ??= PublicationsSortBy::Newest;
        
        $statusEnum = $status == null ? null : EnumExtensions::tryResolve(ReadingStatus::class, $sort->status());
        $statusEnum ??= ReadingStatus::None;

        $statusFilter = is_int($currentUserId) ? new RadingStatusFilters($currentUserId, $statusEnum) : null;

        $page = $pageQ->page();
        $pageSize = $pageQ->pageSize();

        $items = $this->pubsRepo->getList(
            $page, 
            $pageSize, 
            $search, 
            $sortEnum,
            $props->getProps(),
            $genreId, 
            $creatorId,
            $isbn,
            $statusFilter
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }
}