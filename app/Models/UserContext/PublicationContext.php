<?php
declare(strict_types=1);

namespace App\Models\UserContext;

use App\Models\Enums\ReadingStatus;
use App\Models\Dto;
use App\Models\Access\Rules\PublicationAccessRules;

final readonly class PublicationContext extends Dto implements UserContextInterface
{
    public function __construct(
        public bool $isSaved,
        public ReadingStatus $readingStatus,
        public ?int $rating,
        public PublicationAccessRules $rules
    ) {}

    public function toArray(): array
    {
        return [
            'isSaved' => $this->isSaved,
            'readingStatus' => $this->readingStatus->value,
            'rating' => $this->rating,
            'rules' => $this->rules->toArray()
        ];
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $isReading = self::bool($row, $prefix . 'is_reading');
        $readCompl = self::bool($row, $prefix . 'is_read_completed');
        $rating = self::int($row, $prefix . 'rating');
        return new self(
            isSaved: self::bool($row, $prefix . 'is_saved'),
            readingStatus: $readCompl ? ReadingStatus::Ended
                : ($isReading ? ReadingStatus::Reading : ReadingStatus::None),
            rating: $rating > 0 ? $rating : NULL,
            rules: PublicationAccessRules::fromEmpty()
        );
    }

    public static function fromEmpty() : self 
    {
        return new self(
            isSaved: false,
            readingStatus: ReadingStatus::None,
            rating: NULL,
            rules: PublicationAccessRules::fromEmpty()
        );
    }

    public function getRules() { return $this->rules; }
    public function withRules(PublicationAccessRules $rules) : self 
    {
        return new self(
            isSaved: $this->isSaved,
            readingStatus: $this->readingStatus,
            rating: $this->rating,
            rules: $rules
        );
    }
}