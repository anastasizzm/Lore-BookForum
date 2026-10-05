<?php
declare(strict_types=1);

namespace App\Models\UserContext;

use App\Models\Enums\ReadingStatus;
use App\Models\Dto;

final readonly class PublicationContext extends Dto implements UserContextInterface
{
    public function __construct(
        public bool $isSaved = false,
        public bool $isEditor = false,
        public ReadingStatus $readingStatus = ReadingStatus::None,
        public ?int $rating = NULL
    ) {}

    public function toArray(): array
    {
        return [
            'isSaved' => $this->isSaved,
            'isEditor' => $this->isEditor,
            'readingStatus' => $this->readingStatus->value,
            'rating' => $this->rating
        ];
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $isReading = self::bool($row, $prefix . 'is_reading');
        $readCompl = self::bool($row, $prefix . 'is_read_completed');
        $rating = self::int($row, $prefix . 'rating');
        return new self(
            isSaved: self::bool($row, $prefix . 'is_saved'),
            isEditor: self::bool($row, $prefix . 'is_editor'),
            readingStatus: $readCompl ? ReadingStatus::Ended
                : ($isReading ? ReadingStatus::Reading : ReadingStatus::None),
            rating: $rating > 0 ? $rating : NULL
        );
    }
}