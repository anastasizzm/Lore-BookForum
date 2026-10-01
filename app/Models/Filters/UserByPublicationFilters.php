<?php
declare(strict_types=1);

namespace App\Models\Filters;

use App\Models\Enums\ReadingStatus;

final class UserByPublicationFilters
{
    public function __construct(
        private readonly int $userId,
        private readonly ReadingStatus $status,
        private readonly bool $savedOnly = false
    ){}

    public function getUserId() : int { return $this->userId; }
    public function getReadingStatus() : ReadingStatus { return $this->status; }
    public function getSavedOnly() : bool { return $this->savedOnly; }
}