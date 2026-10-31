<?php
declare(strict_types=1);

namespace App\Models\Users;

use App\Models\Users\UserShortData;

use DateTimeImmutable;

readonly class UserData extends UserMediumData
{
    public function __construct(
        int    $id,
        string $username,
        string $name,
        string $surname,
        string $avatar,
        string $email,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $deletedAt,
        public string $bio,
    ) {
        parent::__construct($id, $username, $name, $surname, $avatar, $email, $createdAt, $deletedAt);
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
            email: $parent->email,
            createdAt: $parent->createdAt,
            deletedAt: $parent->deletedAt,
            bio: self::str($row, $prefix . 'bio'),
        );
    }

    public function toArray() : array 
    {
        $parent = parent::toArray();
        return $parent + [
            'bio' => $this->bio,
        ];
    }
}