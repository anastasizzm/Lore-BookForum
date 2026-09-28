<?php
declare(strict_types=1);

namespace App\Models\Users;

use App\Models\Dto;

final readonly class UserContext extends Dto
{
    public function __construct(
        public int     $id,
        public string  $username,
        public string  $name,
        public string  $avatar,
        public bool    $isAdmin,
        public bool    $isRedactor,
    ) {}

    public static function fromRow(string $row, string $prefix = '') : self
    {
        return new self(
            id: self::int($row, $prefix . 'id'),
            name: self::str($row, $prefix, 'name'),
            username: self::str($row, $prefix . 'username'),
            avatar: self::str($row, $prefix, 'avatar'),
            isAdmin: self::bool($row, $prefix, 'is_admin'),
            isRedactor: self::bool($row, $prefix, 'is_redactor'),
        );
    }
}