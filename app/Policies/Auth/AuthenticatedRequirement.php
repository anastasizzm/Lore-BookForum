<?php
declare(strict_types=1);

namespace App\Policies\Auth;

use App\Lib\Auth\AuthorizationRequirement;

final class AuthenticatedRequirement implements AuthorizationRequirement
{
    public function describe() :string { return 'errors.policies.auth'; }
}
