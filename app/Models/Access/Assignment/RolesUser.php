<?php
declare(strict_types=1);

namespace App\Models\Access\Assignment;

use App\Models\Users\UserMediumData;
use App\Models\Additional\UserRole;
use DateTimeImmutable;

readonly class RolesUser extends UserMediumData 
{
    /** @param array<UserRole> $roles */
    public function __construct(
        int     $id,
        string  $username,
        string  $name,
        string  $surname,
        string  $avatar,
        string  $email,
        DateTimeImmutable  $createdAt,
        ?DateTimeImmutable  $deletedAt,
        public array $roles
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
            roles: []
        );
    }

    public function withRoles(array $roles) : self 
    {
        return new self(
            id: $this->id,
            name: $this->name,
            username: $this->username,
            surname: $this->surname,
            avatar: $this->avatar,
            email: $this->email,
            createdAt: $this->createdAt,
            deletedAt: $this->deletedAt,
            roles: $roles
        );
    }

    public function toArray() : array 
    {
        $parent = parent::toArray();
        return $parent + [
            'roles' => $this->roles
        ];
    }
}