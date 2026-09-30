<?php
declare(strict_types=1);

namespace App\Models\Users;

use App\Models\Users\UserShortData;

use DateTimeImmutable;

readonly class UserData extends UserShortData
{
    public function __construct(
        int    $id,
        string $username,
        string $name,
        string $surname,
        string $avatar,
        public string $bio,
        public DateTimeImmutable $createdAt
    ) {
        parent::__construct($id, $username, $name, $surname, $avatar);
    }

    public static function fromRow(array $row, string $prefix = '') : self
    {
        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            name: $parent->name,
            username: $parent->username,
            surname: $parent->surname,
            avatar: $parent->avatar,
            bio: self::str($row, $prefix . 'bio'),
            createdAt: self::dt($row, $prefix . 'created_at')
        );
    }
}