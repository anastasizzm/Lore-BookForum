<?php
declare(strict_types=1);

namespace App\Forms\Permissions;
use App\Extensions\Parsers\QueryParser;
use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Access\Rules\PostAccessRules;
use App\Models\Access\Rules\ShortPublicationAccessRules;

final readonly class PublicationPermissionForm extends PermissionForm
{
    public function __construct(
        int $roleId,
        ?int $belowId,
        ?int $aboveId,
        public ShortPublicationAccessRules $pubPermissions
    ){
        parent::__construct($roleId, $belowId, $aboveId);
    }

    public static function fromInput(array $form) : self 
    {
        $parent = parent::fromInput($form);
        return new self(
            roleId : $parent->roleId,
            belowId: $parent->belowId,
            aboveId: $parent->aboveId,
            pubPermissions: ShortPublicationAccessRules::fromInput($form['pubPermissions'] ?? [])
        );
    }
}