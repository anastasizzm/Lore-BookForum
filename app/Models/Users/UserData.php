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
        public string $email,
        public string $bio,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $deletedAt
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
            email: self::str($row, $prefix . 'email'),
            bio: self::str($row, $prefix . 'bio'),
            createdAt: self::dt($row, $prefix . 'created_at'),
            deletedAt: self::dtN($row, $prefix . 'deleted_at')
        );
    }

    public function toArray() : array 
    {
        $parent = parent::toArray();
        return $parent + [
            'email' => $this->email,
            'bio' => $this->bio,
            'createdAt' => $this->createdAt
        ];
    }

    public function isActive() : bool { return $this->deletedAt === NULL; }
}