<?php
declare(strict_types=1);

namespace App\Models\Queries\Publications;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Filters\Publications\BooksFilters;
use App\Models\Filters\Publications\UserRelationFilters;

final readonly class BooksListQuery
{
    public function __construct(
        public PaginationQuery $pagination,
        public SortQuery $sort,
        public PropertiesQuery $properties,
        public BooksFilters $filters,
        public ?UserRelationFilters $userFilters
    ) {}

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q, int $viewerId): self
    {
        return new self(
            pagination: PaginationQuery::fromInput($q),
            sort: SortQuery::fromInput($q),
            properties: PropertiesQuery::fromInput($q),
            filters: BooksFilters::fromInput($q),
            userFilters: $viewerId !== null
                ? UserRelationFilters::fromInput($q, $viewerId)
                : null,
        );
    }
}