<?php
declare(strict_types=1);

namespace App\Policies\Verified;

use App\Lib\Auth\AuthorizationRequirement;

final class AdminRequirement implements AuthorizationRequirement
{
    public function describe() : string { return 'The user must be admin'; }
}
