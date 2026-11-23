<?php
declare(strict_types=1);

namespace App\Forms\Permissions;
use App\Forms\Form;
use App\Models\Access\Rules\PublicationAccessRules;

final readonly class EditPublicationPermissionForm implements Form
{
    public function __construct(
        public ?PublicationAccessRules $pubPermissions
    ){}

    public static function fromInput(array $form) : self
    {
        $hasPub = isset($form['pubPermissions']);
        return new self(
            pubPermissions: $hasPub ? PublicationAccessRules::fromInput($form['pubPermissions']) : NULL
        );
    }
}
