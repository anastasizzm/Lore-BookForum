<?php
declare(strict_types=1);

namespace App\Forms\Permissions;
use App\Extensions\Parsers\QueryParser;
use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Access\Rules\PostAccessRules;
use App\Models\Access\Rules\PublicationAccessRules;

final readonly class SystemPermissionForm extends PermissionForm
{
    public function __construct(
        int $roleId,
        ?int $belowId,
        ?int $aboveId,
        public SystemAccessRules $systemPermissions,
        public PostAccessRules $postPermissions,
        public PublicationAccessRules $pubPermissions
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
            systemPermissions: SystemAccessRules::fromInput($form['systemPermissions'] ?? []),
            postPermissions: PostAccessRules::fromInput($form['postPermissions'] ?? []),
            pubPermissions: PublicationAccessRules::fromInput($form['pubPermissions'] ?? [])
        );
    }
}