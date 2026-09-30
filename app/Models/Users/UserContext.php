<?php
declare(strict_types=1);

namespace App\Models\Users;

use App\Models\Dto;

final readonly class UserContext extends UserShortData
{
    public function __construct(
        $id,
        $username,
        $name,
        $surname,
        $avatar,
        public bool    $isAdmin,
        public bool    $isRedactor,
    ) {
        parent::__construct($id, $username, $name, $surname, $avatar);
    }

    public static function fromRow(string $row, string $prefix = '') : self
    {
        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            name: $parent->name,
            username: $parent->username,
            avatar: $parent->avatar,
            isAdmin: self::bool($row, $prefix, 'is_admin'),
            isRedactor: self::bool($row, $prefix, 'is_redactor'),
        );
    }
}