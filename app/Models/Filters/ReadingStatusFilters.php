<?php
declare(strict_types=1);

namespace App\Models\Filters;

use App\Models\Enums\ReadingStatus;

final class ReadingStatusFilters
{
    public function __construct(
        private readonly int $userId,
        private readonly ReadingStatus $status
    ){}

    public function getUserId() : int { return $this->userId; }
    public function getReadingStatus() : ReadingStatus { return $this->status; }
}