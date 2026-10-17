<?php
declare(strict_types=1);

namespace App\Models\Access\Rules;
use App\Models\Access\Operations\AccessOperation;

use App\Models\Access\Operations\System\AdminAccessOperation;
use App\Models\Access\Operations\System\ManageAccountsSystemOperation;
use App\Models\Access\Operations\System\ManageAdditionalsSystemOperation;
use App\Models\Access\Operations\System\ManagePeopleSystemOperation;
use App\Models\Access\Operations\System\ManageRolesSystemOperation;

use App\Models\Dto;

readonly class SystemAccessRules extends Dto
{
    public function __construct(
        public bool $manageAccounts,
        public bool $manageAdditionals,
        public bool $managePeople,
        public bool $manageRoles,
    ){}

    public function hasAny() : bool 
    {
        return $this->manageAccounts 
            || $this->manageAdditionals 
            || $this->managePeople
            || $this->manageRoles;
    }

    public function allows(AccessOperation $operation) : bool
    {
        return match(true) {
            !($operation instanceof AdminAccessOperation) => false,
            $operation instanceof ManageAccountsSystemOperation => $this->manageAccounts,
            $operation instanceof ManageAdditionalsSystemOperation => $this->manageAdditionals,
            $operation instanceof ManagePeopleSystemOperation => $this->managePeople,
            $operation instanceof ManageRolesSystemOperation => $this->manageRoles,
            default => false,
        };
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            manageAccounts: self::bool($row, $prefix . 'manage_accounts'),
            manageAdditionals: self::bool($row, $prefix . 'manage_additionals'),
            managePeople: self::bool($row, $prefix . 'manage_people'),
            manageRoles: self::bool($row, $prefix . 'manage_roles'),
        );
    }
}