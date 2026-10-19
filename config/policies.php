<?php
declare(strict_types=1);

use App\Lib\Auth\AuthPolicy;

use App\Policies\Auth\AuthenticatedHandler;
use App\Policies\Auth\AuthenticatedRequirement;

use App\Policies\Verified\VerifiedHandler;
use App\Policies\Verified\VerifiedRequirement;

use App\Policies\Admin\AdminHandler;
use App\Policies\Admin\AdminRequirement;

use App\Policies\Owner\Profile\ProfileOwnerHandler;
use App\Policies\Owner\Profile\ProfileOwnerRequirement;

use App\Policies\Owner\Post\PostOwnerHandler;
use App\Policies\Owner\Post\PostOwnerRequirement;

use App\Policies\Access\AccessHandler;
use App\Policies\Access\System\SystemAccessRequirement;
use App\Policies\Access\Post\PostAccessRequirement;
use App\Policies\Access\Publication\PublicationAccessRequirement;

$registry->registerPolicy(AuthPolicy::Auth, fn($ctx) => new AuthenticatedRequirement(), AuthenticatedHandler::class);
$registry->registerPolicy(AuthPolicy::Verified, fn($ctx) => new VerifiedRequirement(), VerifiedHandler::class);
$registry->registerPolicy(AuthPolicy::Admin, fn($ctx) => new AdminRequirement(), AdminHandler::class);
$registry->registerPolicy('profile_owner', fn($ctx) => new ProfileOwnerRequirement(), ProfileOwnerHandler::class);
$registry->registerPolicy('post_owner', fn($ctx) => new PostOwnerRequirement(), PostOwnerHandler::class);

foreach([
    PublicationAccessRequirement::class => [
        'pattern' => "#(books|articles)\/(\d+)#",
        'operations' => [
            App\Models\Access\Operations\Publications\CreatePublicationOperation::class,
            App\Models\Access\Operations\Publications\DeletePublicationOperation::class,
            App\Models\Access\Operations\Publications\UpdateMetaPublicationOperation::class,
            App\Models\Access\Operations\Publications\UpdateContentPublicationOperation::class,
            App\Models\Access\Operations\Publications\ReadPublicationOperation::class,
            App\Models\Access\Operations\Publications\ManagePeoplePublicationOperation::class,
            App\Models\Access\Operations\Publications\ManageRolesPublicationOperation::class,
        ]
    ],

    PostAccessRequirement::class => [
        'pattern' => "#posts\/(\d+)#",
        'operations' => [
            App\Models\Access\Operations\Posts\CreatePostOperation::class,
            App\Models\Access\Operations\Posts\DeletePostOperation::class,
            App\Models\Access\Operations\Posts\UpdatePostOperation::class,
        ]
    ]
] as $req => $args)
{
    foreach($args['operations'] as $op)
    {
        $registry->registerPolicy(
            $op, 
            fn($ctx) => new $req($args['pattern'], $op),
            AccessHandler::class);
    }
}

use App\Models\Access\Operations\System\ManageAccountsSystemOperation;
use App\Models\Access\Operations\System\ManageAdditionalsSystemOperation;
use App\Models\Access\Operations\System\ManagePeopleSystemOperation;
use App\Models\Access\Operations\System\ManageRolesSystemOperation;

$registry->registerPolicy(
    ManageAdditionalsSystemOperation::class, 
    fn($ctx) => new SystemAccessRequirement('#additionals\/#', ManageAdditionalsSystemOperation::class),
    AccessHandler::class);
$registry->registerPolicy(
    ManagePeopleSystemOperation::class, 
    fn($ctx) => new SystemAccessRequirement('#system\/users\/#', ManagePeopleSystemOperation::class),
    AccessHandler::class);
$registry->registerPolicy(
    ManageRolesSystemOperation::class, 
    fn($ctx) => new SystemAccessRequirement('#system\/roles\/#', ManageRolesSystemOperation::class),
    AccessHandler::class);
