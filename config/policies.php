<?php
declare(strict_types=1);

use App\Lib\Auth\AuthPolicy;

use App\Policies\Auth\AuthenticatedHandler;
use App\Policies\Auth\AuthenticatedRequirement;

use App\Policies\Verified\VerifiedHandler;
use App\Policies\Verified\VerifiedRequirement;

$registry->registerPolicy(AuthPolicy::Auth, fn($ctx) => new AuthenticatedRequirement(), AuthenticatedHandler::class);
$registry->registerPolicy(AuthPolicy::Verified, fn($ctx) => new VerifiedRequirement(), VerifiedHandler::class);