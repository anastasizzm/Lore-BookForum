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
        private SystemAccessRules $systemRules
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

    public function toArray() : array 
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'surname' => $this->surname,
            'avatar' => $this->avatar,
            'systemRules' => $this->systemRules->toArray()
        ];
    }

    public function fromArray(array $data) : static 
    {
        $systemRules = $data['systemRules'] ?? NULL;
        return new self(
            id: (int) $data['id'],
            username: (string) $data['username'],
            name: (string) ($data['name'] ?? ''),
            avatar: (string) ($data['avatar'] ?? 'default'),
            surname: (string)($data['surname'] ?? ''),
            systemRules:  $systemRules === NULL ? SystemAccessRules::fromEmpty() : SystemAccessRules::fromArray($systemRules)
        );
    }
}