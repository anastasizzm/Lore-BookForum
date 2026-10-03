<?php
declare(strict_types=1);

namespace App\Models\Criterias\Publications;

use App\Models\Enums\PublicationsSortBy;
use App\Models\Filters\Publications\BooksFilters;
use App\Models\Criterias\Publications\UserRelationCriteria;

final readonly class BooksCriteria
{
    public function __construct(
        public int $page,
        public int $pageSize,
        public PublicationsSortBy $sortBy,
        public BooksFilters $filters,
        public ?UserRelationCriteria $userCriteria = null,
    ) {}
}