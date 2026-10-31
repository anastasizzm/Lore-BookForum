<?php
declare(strict_types=1);

namespace App\Models\Criterias\Users;

use App\Models\Enums\UsersSortBy;

final readonly class UsersCriteria
{
    public function __construct(
        public int $page,
        public int $pageSize,
        public string $classType,
        public UsersSortBy $sortBy,
        public ?string $search
    ) {}
}