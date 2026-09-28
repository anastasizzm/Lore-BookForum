<?php
declare(strict_types=1);

namespace App\Models\Publications;

use DateTimeImmutable;

use App\Models\Dto;

readonly class PublicationShort extends Dto
{
    public const ROW_PREFIX = 'pub_';

    public function __construct(
        public int $id,
        public string $title,
        public int $iconId,
        public DateTimeImmutable $createdAt
    ){}

    public static function fromRow(string $row, string $prefix = '') : self 
    {
        return new self(
            id: self::int($row, $prefix, 'id'),
            title: self::str($row, $prefix, 'title'),
            iconId: self::uuidN($row, $prefix, 'icon_id'),
            createdAt: self::dt($row, $prefix . 'created_at'),
        );
    }
}