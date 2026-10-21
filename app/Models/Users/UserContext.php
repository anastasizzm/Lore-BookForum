<?php
declare(strict_types=1);

namespace App\Models\Users;

use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Dto;

final readonly class UserContext extends UserShortData
{
    public function __construct(
        int $id,
        string $username,
        string $name,
        string $surname,
        string $avatar,
        SystemAccessRules $systemRules
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
            systemRules: SystemAccessRules::fromEmpty()
        );
    }

    public function getSystemRules() { return $this->systemRules; }
    public function withRules(SystemAccessRules $rules) : self 
    {
        return new self(
            id: $this->id,
            username: $this->username,
            name: $this->name,
            surname: $this->surname,
            avatar: $this->avatar,
            systemRules: $rules
        );
    }
}