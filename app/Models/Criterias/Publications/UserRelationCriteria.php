<?php
declare(strict_types=1);

namespace App\Models\Criterias\Publications;

use App\Models\Enums\ReadingStatus;

final readonly class UserRelationCriteria
{
    public function __construct(
        public int $viewerId,
        public ReadingStatus $status = ReadingStatus::None,
        public bool $savedOnly = false,
    ) {}
}