<?php
declare(strict_types=1);

namespace App\Models\Queries\Permissions;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Extensions\Parsers\QueryParser;
use App\Models\Queries\PropertiesQuery;

final readonly class RolesUserListQuery
{
    public function __construct(
        public PaginationQuery $pagination,
        public SortQuery $sort,
        public PropertiesQuery $properties,
        public ?int $roleId
    ) {}

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q): self
    {
        return new self(
            pagination: PaginationQuery::fromInput($q),
            sort: SortQuery::fromInput($q),
            properties: PropertiesQuery::fromInput($q),
            roleId: QueryParser::optionalPositiveInt($q, 'role')
        );
    }

    public static function fromCustomRole(array $q, int $roleId) : self 
    {
        return new self(
            pagination: PaginationQuery::fromInput($q),
            sort: SortQuery::fromInput($q),
            properties: PropertiesQuery::fromInput($q),
            roleId: $roleId
        );
    }
}