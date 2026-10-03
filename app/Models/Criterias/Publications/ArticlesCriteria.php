<?php
declare(strict_types=1);

namespace App\Models\Criterias\Publications;

use App\Models\Enums\PublicationsSortBy;
use App\Models\Filters\Publications\ArticlesFilters;
use App\Models\Criterias\Publications\UserRelationCriteria;

final readonly class ArticlesCriteria
{
    public function __construct(
        public int $page,
        public int $pageSize,
        public PublicationsSortBy $sortBy,
        public ArticlesFilters $filters,
        public ?UserRelationCriteria $userCriteria = null,
    ) {}
}