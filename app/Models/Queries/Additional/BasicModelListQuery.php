<?php
declare(strict_types=1);

namespace App\Models\Queries\Additional;

use App\Extensions\Parsers\QueryParser;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\PropertiesQuery;

final readonly class BasicModelListQuery
{
    public function __construct(
        public PaginationQuery $pagination,
        public SortQuery $sort,
        public string $search,
    ) {}

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q): self
    {
        return new self(
            pagination: PaginationQuery::fromInput($q),
            sort: SortQuery::fromInput($q),
            search: QueryParser::optionalString($q, 'q')
        );
    }
}