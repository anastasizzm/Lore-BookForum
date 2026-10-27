<?php
declare(strict_types=1);

namespace App\Forms\Permissions;
use App\Forms\Form;
use App\Extensions\Parsers\QueryParser;
use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Access\Rules\PostAccessRules;
use App\Models\Access\Rules\PublicationAccessRules;

final readonly class EditPermissionForm implements Form
{
    public function __construct(
        public ?SystemAccessRules $systemPermissions,
        public ?PostAccessRules $postPermissions,
        public ?PublicationAccessRules $pubPermissions
    ){}

    public static function fromInput(array $form) : self 
    {
        $hasSystem = isset($form['systemPermissions']);
        $hasPost = isset($form['postPermissions']);
        $hasPub = isset($form['pubPermissions']);
        return new self(
            systemPermissions: $hasSystem ? SystemAccessRules::fromInput($form['systemPermissions']) : NULL,
            postPermissions: $hasPost ? PostAccessRules::fromInput($form['postPermissions']) : NULL,
            pubPermissions: $hasPub ? PublicationAccessRules::fromInput($form['pubPermissions']) : NULL
        );
    }
}