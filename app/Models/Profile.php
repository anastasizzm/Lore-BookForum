<?php
declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use Dto;

final readonly class Profile extends Dto
{
    public const ROW_PREFIX = 'p_';

    public function __construct(
        public int $id,
        public string $name,
        public string $surname,
        public string $bio,
        public string $icon,
        public DateTimeImmutable $createdAt
    ){}

    public static function fromRow(array $row, string $prefix = '') : self
    {
        return new self(
            id: self::int($row, $prefix . 'id'),
            name: self::str($row, $prefix . 'name'),
            surname: self::str($row, $prefix . 'surname'),
            bio: self::str($row, $prefix . 'bio'),
            icon: self::str($row, $prefix . 'icon'),
            createdAt: self::dt($row, $prefix . 'created_at')
        );
    }
}