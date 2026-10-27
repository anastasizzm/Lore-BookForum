<?php
declare(strict_types=1);

namespace App\Models\Access\Rules;
use App\Models\Access\Operations\AccessOperation;
use App\Extensions\Parsers\QueryParser;

use App\Models\Access\Operations\Publications\PublicationAccessOperation;
use App\Models\Access\Operations\Publications\CreatePublicationOperation;
use App\Models\Access\Operations\Publications\UpdateMetaPublicationOperation;
use App\Models\Access\Operations\Publications\UpdateContentPublicationOperation;
use App\Models\Access\Operations\Publications\DeletePublicationOperation;
use App\Models\Access\Operations\Publications\ReadPublicationOperation;
use App\Models\Access\Operations\Publications\ManagePeoplePublicationOperation;
use App\Models\Access\Operations\Publications\ManageRolesPublicationOperation;
use App\Models\Access\Operations\Publications\AnyPublicationOperation;

use App\Forms\Form;
use App\Models\RowModel;

readonly class PublicationAccessRules extends RowModel implements OperationChecker, Form, HasRolesManage
{
    public function __construct(
        public bool $create,
        public bool $updateMeta,
        public bool $updateContent,
        public bool $delete,
        public bool $read,
        public bool $managePeople,
        public bool $manageRoles
    ){}

    public function hasAny() : bool 
    {
        return $this->create || $this->updateMeta 
            || $this->updateContent || $this->updateMeta
            || $this->delete || $this->read
            || $this->managePeople || $this->manageRoles;
    }

    public function allows(AccessOperation $operation) : bool
    {
        return match(true) {
            !($operation instanceof PublicationAccessOperation) => false,
            $operation instanceof CreatePublicationOperation => $this->create,
            $operation instanceof UpdateMetaPublicationOperation => $this->updateMeta,
            $operation instanceof UpdateContentPublicationOperation => $this->updateContent,
            $operation instanceof DeletePublicationOperation => $this->delete,
            $operation instanceof ReadPublicationOperation => $this->read,
            $operation instanceof ManagePeoplePublicationOperation => $this->managePeople,
            $operation instanceof ManageRolesPublicationOperation => $this->manageRoles,
            $operation instanceof AnyPublicationOperation => $this->hasAny(),
            default => false,
        };
    }

    public function hasManagePeople() : bool { return $this->managePeople; }
    public function hasManageRoles() : bool { return $this->manageRoles; }

    public static function fromEmpty() : self 
    {
        return new self(
            false, false, false, false , false, false, false
        );
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            create: self::bool($row, $prefix . 'create'),
            updateMeta: self::bool($row, $prefix . 'update_meta'),
            updateContent: self::bool($row, $prefix . 'update_content'),
            delete: self::bool($row, $prefix . 'delete'),
            read: self::bool($row, $prefix . 'read'),
            managePeople: self::bool($row, $prefix . 'manage_ppl'),
            manageRoles: self::bool($row, $prefix . 'manage_roles'),
        );
    }

    public static function fromInput(array $form) : self 
    {
        return new self(
            create: QueryParser::optionalBool($form, 'create') ?? false,
            updateMeta: QueryParser::optionalBool($form, 'updateMeta') ?? false,
            updateContent: QueryParser::optionalBool($form, 'updateContent') ?? false,
            delete: QueryParser::optionalBool($form, 'delete') ?? false,
            read: QueryParser::optionalBool($form, 'read') ?? false,
            manageRoles: QueryParser::optionalBool($form, 'manageRoles') ?? false,
            managePeople: QueryParser::optionalBool($form, 'managePeople') ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'create' => $this->create,
            'updateMeta' => $this->updateMeta,
            'updateContent' => $this->updateContent,
            'delete' => $this->delete,
            'read' => $this->read,
            'managePeople' => $this->managePeople,
            'manageRoles' => $this->manageRoles
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self (
            create: (bool)($data['create'] ?? false),
            updateMeta: (bool)($data['updateMeta'] ?? false),
            updateContent: (bool)($data['updateContent'] ?? false),
            delete: (bool)($data['delete'] ?? false),
            read: (bool)($data['read'] ?? false),
            managePeople: (bool)($data['managePeople'] ?? false),
            manageRoles: (bool)($data['manageRoles'] ?? false)
        );
    }
}