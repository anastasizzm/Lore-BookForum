<?php
declare(strict_types=1);

namespace App\Models\Criterias\Publications;

use App\Models\Enums\PostsSortBy;
use App\Models\Filters\Publications\PostsFilters;
use App\Models\Criterias\Publications\UserRelationCriteria;

final readonly class PostsCriteria
{
    public function __construct(
        public int $page,
        public int $pageSize,
        public PostsSortBy $sortBy,
        public PostsFilters $filters
    ) {}
}