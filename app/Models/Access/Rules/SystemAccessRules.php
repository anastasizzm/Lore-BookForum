<?php
declare(strict_types=1);

namespace App\Models\Access\Rules;
use App\Models\Access\Operations\AccessOperation;
use App\Extensions\Parsers\QueryParser;

use App\Models\Access\Operations\System\AdminAccessOperation;
use App\Models\Access\Operations\System\ManageAdditionalsSystemOperation;
use App\Models\Access\Operations\System\AnySystemOperation;
use App\Models\Access\Operations\System\ManagePeopleSystemOperation;
use App\Models\Access\Operations\System\ManageRolesSystemOperation;

use App\Forms\Form;
use App\Models\Dto;

readonly class SystemAccessRules extends Dto implements OperationChecker, Form
{
    public function __construct(
        public bool $manageAccounts,
        public bool $manageAdditionals,
        public bool $managePeople,
        public bool $manageRoles,
    ){}

    public function hasAny() : bool 
    {
        return $this->manageAdditionals
            || $this->manageAccounts 
            || $this->managePeople
            || $this->manageRoles;
    }

    public function allows(AccessOperation $operation) : bool
    {
        return match(true) {
            !($operation instanceof AdminAccessOperation) => false,
            $operation instanceof ManageAdditionalsSystemOperation => $this->manageAdditionals,
            $operation instanceof ManagePeopleSystemOperation => $this->managePeople,
            $operation instanceof ManageRolesSystemOperation => $this->manageRoles,
            $operation instanceof AnySystemOperation => $this->hasAny(),
            default => false,
        };
    }

    public static function fromEmpty() : self 
    {
        return new self(
            false, false, false, false
        );
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            manageAccounts: self::bool($row, $prefix . 'manage_accounts'),
            manageAdditionals: self::bool($row, $prefix . 'manage_additionals'),
            managePeople: self::bool($row, $prefix . 'manage_ppl'),
            manageRoles: self::bool($row, $prefix . 'manage_roles'),
        );
    }

    public static function fromInput(array $form) : self 
    {
        return new self(
            manageAccounts: QueryParser::optionalBool($form, 'manageAccounts') ?? false,
            manageAdditionals: QueryParser::optionalBool($form, 'manageAdditionals') ?? false,
            managePeople: QueryParser::optionalBool($form, 'managePeople') ?? false,
            manageRoles: QueryParser::optionalBool($form, 'manageRoles') ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'manageAccounts' => $this->manageAccounts,
            'manageAdditionals' => $this->manageAdditionals,
            'managePeople' => $this->managePeople,
            'manageRoles' => $this->manageRoles
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            manageAccounts: (bool)($data['manageAccounts'] ?? false),
            manageAdditionals: (bool)($data['manageAdditionals'] ?? false),
            managePeople: (bool)($data['managePeople'] ?? false),
            manageRoles: (bool)($data['manageRoles'] ?? false)
        );
    }
}