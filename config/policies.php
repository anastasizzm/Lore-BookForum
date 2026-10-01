<?php
declare(strict_types=1);

use App\Lib\Auth\AuthPolicy;

use App\Policies\Auth\AuthenticatedHandler;
use App\Policies\Auth\AuthenticatedRequirement;

use App\Policies\Verified\VerifiedHandler;
use App\Policies\Verified\VerifiedRequirement;

use App\Policies\Owner\Profile\ProfileOwnerHandler;
use App\Policies\Owner\Profile\ProfileOwnerRequirement;

use App\Policies\Owner\Post\PostOwnerHandler;
use App\Policies\Owner\Post\PostOwnerRequirement;

$registry->registerPolicy(AuthPolicy::Auth, fn($ctx) => new AuthenticatedRequirement(), AuthenticatedHandler::class);
$registry->registerPolicy(AuthPolicy::Verified, fn($ctx) => new VerifiedRequirement(), VerifiedHandler::class);
$registry->registerPolicy(AuthPolicy::Admin, fn($ctx) => new AdminRequirement(), AdminHandler::class);
$registry->registerPolicy('profile_owner', fn($ctx) => new ProfileOwnerRequirement(), ProfileOwnerHandler::class);
$registry->registerPolict('post_owner', fn($ctx) => new PostOwnerRequirement(), PostOwnerHandler::class);