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
use App\Models\Access\Operations\Publications\ControlPublicationOperation;

use App\Forms\Form;
use App\Models\RowModel;

readonly class PublicationAccessRules extends ShortPublicationAccessRules
{
    public function __construct(
        bool $create,
        bool $updateMeta,
        bool $updateContent,
        bool $read,
        bool $managePeople,
        bool $manageRoles,
        public bool $delete,
    ){
        parent::__construct($create, $updateMeta, $updateContent, $read, $managePeople, $manageRoles);
    }

    public function hasAny() : bool 
    {
        return parent::hasAny() || $this->delete;
    }

    public function allows(AccessOperation $operation) : bool
    {
        return match(true) {
            $operation instanceof DeletePublicationOperation => $this->delete,
            $operation instanceof ControlPublicationOperation => $this->updateMeta || $this->updateContent || $this->delete || $this->read,
            default => parent::allows($operation),
        };
    }

    public static function fromEmpty() : self 
    {
        return new self(
            false, false, false, false, false, false, false
        );
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $parent = parent::fromRow($row, $prefix);
        return new self(
            create: $parent->create,
            updateMeta: $parent->updateMeta,
            updateContent: $parent->updateContent,
            delete: self::bool($row, $prefix . 'delete'),
            read: $parent->read,
            managePeople: $parent->managePeople,
            manageRoles: $parent->manageRoles,
        );
    }

    public static function fromInput(array $form) : self 
    {
        $parent = parent::fromInput($form);
        return new self(
            create: $parent->create,
            updateMeta: $parent->updateMeta,
            updateContent: $parent->updateContent,
            read: $parent->read,
            managePeople: $parent->managePeople,
            manageRoles: $parent->manageRoles,
            delete: QueryParser::optionalBool($form, 'delete') ?? false,
        );
    }

    public function toArray(): array
    {
        return parent::toArray() + [
            'delete' => $this->delete,
        ];
    }

    public static function fromArray(array $data): self
    {
        $parent = parent::fromArray($data);
        return new self (
            create: $parent->create,
            updateMeta: $parent->updateMeta,
            updateContent: $parent->updateContent,
            read: $parent->read,
            managePeople: $parent->managePeople,
            manageRoles: $parent->manageRoles,
            delete: (bool)($data['delete'] ?? false),
        );
    }
}