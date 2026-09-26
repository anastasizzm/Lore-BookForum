<?php
declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use Profile;
use Dto;

final readonly class User extends Dto
{
    public function __construct(
        public int $id,
        public string $userName,
        public string $email,
        public DateTimeImmutable $createdAt,
        public ?Profile $profile
    ) {}

    public static function fromRow(array $row, string $prefix = '') : self
    {
        $p = Profile::ROW_PREFIX;

        return new self(
            id: self::id($row, $prefix . 'id'),
            email: self::str($row, $prefix . 'email'),
            userName: self::str($row, $prefix . 'username'),
            createdAt: self::dt($row, $prefix . 'created_at'),
            profile: self::hasGroup($row, $p, 'id')
                ? Profile::fromRow($row, $p) : null
        );
    }
}