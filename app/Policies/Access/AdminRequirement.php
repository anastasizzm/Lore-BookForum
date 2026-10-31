<?php
declare(strict_types=1);

namespace App\Policies\Access;

use App\Lib\Auth\AuthorizationRequirement;
use App\Models\Access\Operations\AccessOperation;

class AdminRequirement implements AuthorizationRequirement
{
    public function describe() : string { return 'errors.policies.admin'; }
}
