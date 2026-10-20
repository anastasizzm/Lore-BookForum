<?php
declare(strict_types=1);

use App\Lib\Auth\AuthPolicy;

use App\Policies\Auth\AuthenticatedHandler;
use App\Policies\Auth\AuthenticatedRequirement;

use App\Policies\Verified\VerifiedHandler;
use App\Policies\Verified\VerifiedRequirement;

use App\Policies\Access\AccessHandler;
use App\Policies\Access\System\SystemAccessRequirement;
use App\Policies\Access\Post\PostAccessRequirement;
use App\Policies\Access\Publication\PublicationAccessRequirement;
use App\Policies\Access\Account\AccountAccessRequirement;

$registry->registerPolicy(AuthPolicy::Auth, fn($ctx) => new AuthenticatedRequirement(), AuthenticatedHandler::class);
$registry->registerPolicy(AuthPolicy::Verified, fn($ctx) => new VerifiedRequirement(), VerifiedHandler::class);

// Concrete Access
define('PUB_PATTERN_CONCRETE', "#(books|articles)\/(\d+)#");
define('POST_PATTERN_CONCRETE', "#posts\/(\d+)#");
define('ACC_PATTERN_CONCRETE', "#users\/(\d+)#");

foreach([
    PublicationAccessRequirement::class => [
        'pattern' => PUB_PATTERN_CONCRETE,
        'operations' => [
            App\Models\Access\Operations\Publications\DeletePublicationOperation::class,
            App\Models\Access\Operations\Publications\UpdateMetaPublicationOperation::class,
            App\Models\Access\Operations\Publications\UpdateContentPublicationOperation::class,
            App\Models\Access\Operations\Publications\ReadPublicationOperation::class,
            App\Models\Access\Operations\Publications\ManagePeoplePublicationOperation::class,
            App\Models\Access\Operations\Publications\ManageRolesPublicationOperation::class,
        ]
    ],

    PostAccessRequirement::class => [
        'pattern' => POST_PATTERN_CONCRETE,
        'operations' => [
            App\Models\Access\Operations\Posts\DeletePostOperation::class,
            App\Models\Access\Operations\Posts\UpdatePostOperation::class,
        ]
    ],

    AccountAccessRequirement::class => [
        'pattern' => ACC_PATTERN_CONCRETE,
        'operations' => [
            App\Models\Access\Operations\Accounts\BlockAccountOperation::class,
            App\Models\Access\Operations\Accounts\ManageAccountOperation::class,
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

// Common Access
define('PUB_PATTERN_COMMON', "#(books|articles)\/#");
define('POST_PATTERN_COMMON', "#posts\/#");
define('ACC_PATTERN_COMMON', "#users\/#");

use App\Models\Access\Operations\Publications\CreatePublicationOperation;
use App\Models\Access\Operations\Posts\CreatePostOperation;

$registry->registerPolicy(
    CreatePublicationOperation::class, 
    fn($ctx) => new SystemAccessRequirement(PUB_PATTERN_COMMON, CreatePublicationOperation::class),
    AccessHandler::class);
$registry->registerPolicy(
    CreatePostOperation::class, 
    fn($ctx) => new SystemAccessRequirement(POST_PATTERN_COMMON, CreatePostOperation::class),
    AccessHandler::class);

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
