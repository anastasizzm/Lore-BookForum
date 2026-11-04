<?php
declare(strict_types=1);

namespace App\Forms\Permissions;
use App\Forms\Form;
use App\Extensions\Parsers\QueryParser;
use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Access\Rules\PostAccessRules;
use App\Models\Access\Rules\PublicationAccessRules;

final readonly class EditPublicationPermissionForm implements Form
{
    public function __construct(
        public ?ShortPublicationAccessRules $pubPermissions
    ){}

    public static function fromInput(array $form) : self 
    {
        $hasPub = isset($form['pubPermissions']);
        return new self(
            pubPermissions: $hasPub ? ShortPublicationAccessRules::fromInput($form['pubPermissions']) : NULL
        );
    }
}