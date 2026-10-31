<?php
declare(strict_types=1);

namespace App\Models\Access\Assignment;

use App\Models\Users\UserMediumData;
use App\Models\Additional\UserRole;
use DateTimeImmutable;

readonly class RolesUserSystem extends RolesUser 
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
        array $roles,
        public bool $isVerified,
        public bool $isBlocked,
    ) {
        parent::__construct($id, $username, $name, $surname, $avatar, $email, $createdAt, $deletedAt, $roles);
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
            roles: $parent->roles,
            isVerified: self::bool($row, $prefix . 'is_verified'),
            isBlocked: self::bool($row, $prefix . 'is_blocked')
        );
    }

    public function withRoles(array $roles) : self 
    {
        $parent = parent::withRoles($roles);
        return new self(
            id: $parent->id,
            name: $parent->name,
            username: $parent->username,
            surname: $parent->surname,
            avatar: $parent->avatar,
            email: $parent->email,
            createdAt: $parent->createdAt,
            deletedAt: $parent->deletedAt,
            isBlocked: $this->isBlocked,
            isVerified: $this->isVerified,
            roles: $parent->roles
        );
    }

    public function toArray() : array 
    {
        $parent = parent::toArray();
        return $parent + [
            'isVerified' => $this->isVerified,
            'isBlocked' => $this->isBlocked
        ];
    }
}