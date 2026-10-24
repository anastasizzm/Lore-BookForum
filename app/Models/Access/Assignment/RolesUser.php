<?php
declare(strict_types=1);

namespace App\Models\Access\Assignment;

use App\Models\Users\UserShortData;
use App\Models\Additional\UserRole;

readonly class RolesUser extends UserShortData 
{
    /** @param array<UserRole> $roles */
    public function __construct(
        int     $id,
        string  $username,
        string  $name,
        string  $surname,
        string  $avatar,
        public array $roles
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
            roles: $roles
        );
    }
}