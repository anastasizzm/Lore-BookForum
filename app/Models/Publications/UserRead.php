<?php
declare(strict_types=1);

namespace App\Models\Publications;

use App\Models\Enums\BookFormat;
use App\Models\Dto;
use DateTimeImmutable;

final readonly class UserRead extends Dto
{
    public function __construct(
        public int                $userId,
        public int                $publicationId,
        public BookFormat         $format,
        public array              $position,
        public bool               $isFinished,
        public DateTimeImmutable  $lastReadAt,
    ) {}
    
    /** @return [int, int] */
    public function id() : mixed { return [$this->userId, $this->publicationId]; }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row, string $prefix = ''): self
    {
        $rawPosition = self::str($row, $prefix . 'position');
        $position    = json_decode($rawPosition, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            userId:        self::int($row, $prefix . 'user_id'),
            publicationId: self::int($row, $prefix . 'publication_id'),
            format:        self::enum($row, $prefix . 'format', BookFormat::class),
            position:      is_array($position) ? $position : ['pageNumber' => 1],
            isFinished:    self::bool($row, $prefix . 'is_finished'),
            lastReadAt:    self::dt($row, $prefix . 'last_read_at'),
        );
    }
}