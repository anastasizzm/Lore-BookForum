<?php
declare(strict_types=1);

namespace App\Models\Auth;

use App\Models\Dto;

final readonly class AccountCredits extends Dto
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
    ) {}

    public static function fromRow(array $row, string $prefix = '') : self
    {
        return new self(
            id: self::int($row, $prefix . 'id'),
            username: self::str($row, $prefix . 'username'),
            email: self::str($row, $prefix . 'email'),
        );
    }
}