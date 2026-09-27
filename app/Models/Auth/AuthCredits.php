<?php
declare(strict_types=1);

namespace App\Models\Auth;

use App\Models\Dto;

final readonly class AuthCredits extends Dto
{
    public function __construct(
        public int $id,
        public string $password,
        public bool $isBlocked,
        public bool $isVerified,
    ) {}

    public static function fromRow(array $row, string $prefix = '') : self
    {
        return new self(
            id: self::int($row, $prefix . 'id'),
            password: self::str($row, $prefix . 'pass_hash'),
            isBlocked: self::bool($row, $prefix . 'is_blocked'),
            isVerified: self::bool($row, $prefix . 'is_verified'),
        );
    }
}