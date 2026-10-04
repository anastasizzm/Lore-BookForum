<?php
declare(strict_types=1);

namespace App\Models\Queries\Publications;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Filters\Publications\PostsFilters;

final readonly class PostsListQuery
{
    public function __construct(
        public PaginationQuery $pagination,
        public SortQuery $sort,
        public PropertiesQuery $properties,
        public PostsFilters $filters
    ) {}

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q): self
    {
        return new self(
            pagination: PaginationQuery::fromInput($q),
            sort: SortQuery::fromInput($q),
            properties: PropertiesQuery::fromInput($q),
            filters: PostsFilters::fromInput($q),
        );
    }
}