<?php
declare(strict_types=1);

namespace App\Models\Queries\Users;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Extensions\Parsers\QueryParser;

final readonly class UsersListQuery
{
    public function __construct(
        public PaginationQuery $pagination,
        public ?string $search,
        public SortQuery $sort
    ) {}

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q, int $viewerId): self
    {
        return new self(
            pagination: PaginationQuery::fromInput($q),
            search: QueryParser::optionalString($q, 'q'),
            sort: SortQuery::fromInput($q)
        );
    }
}