<?php
declare(strict_types=1);

use App\Lib\Auth\AuthPolicy;

use App\Policies\Auth\AuthenticatedHandler;
use App\Policies\Auth\AuthenticatedRequirement;

use App\Policies\Auth\VerifiedHandler;
use App\Policies\Auth\VerifiedRequirement;

$registry->registerPolicy(AuthPolicy::Auth, fn($ctx) => new AuthenticatedRequirement(), AuthenticatedHandler::class);
$registry->register(AuthPolicy::Verified->value, fn($ctx) => new VerifiedRequirement(), VerifiedHandler::class);