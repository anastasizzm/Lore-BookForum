<?php
declare(strict_types=1);

namespace App\Models\Users;

use App\Models\Access\Permissions\RulesCombination;
use App\Models\Dto;

final readonly class UserContext extends UserShortData
{
    public function __construct(
        int $id,
        string $username,
        string $name,
        string $surname,
        string $avatar,
        private RulesCombination $rules
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
            rules: RulesCombination::fromEmpty()
        );
    }

    public function getrules() { return $this->rules; }
    public function withRules(RulesCombination $rules) : self 
    {
        return new self(
            id: $this->id,
            username: $this->username,
            name: $this->name,
            surname: $this->surname,
            avatar: $this->avatar,
            rules: $rules
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
            'rules' => $this->rules->toArray()
        ];
    }

    public function fromArray(array $data) : static 
    {
        $rules = $data['rules'] ?? NULL;
        return new self(
            id: (int) $data['id'],
            username: (string) $data['username'],
            name: (string) ($data['name'] ?? ''),
            avatar: (string) ($data['avatar'] ?? 'default'),
            surname: (string)($data['surname'] ?? ''),
            rules:  $rules === NULL ? RulesCombination::fromEmpty() : RulesCombination::fromArray($rules)
        );
    }
}