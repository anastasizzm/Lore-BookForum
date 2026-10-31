<?php
declare(strict_types=1);

namespace App\Models\Queries\Additional;

use App\Extensions\Parsers\QueryParser;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\StatusQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Enums\RoleStatus;

final readonly class UserRoleListQuery extends BasicModelListQuery
{
    public function __construct(
        PaginationQuery $pagination,
        SortQuery $sort,
        ?string $search,
        StatusQuery $status
    ) {
        parent::__construct($pagination, $sort, $search);
    }

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q): self
    {
        $parent = parent::fromInput($q);
        return new self(
            pagination: $parent->pagination,
            sort: $parent->sort,
            search: $parent->search,
            status: StatusQuery::fromInput($q)
        );
    }
}