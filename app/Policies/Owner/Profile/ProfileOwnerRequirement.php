<?php
declare(strict_types=1);

namespace App\Policies\Owner\Profile;

use App\Lib\Auth\AuthorizationRequirement;

final class ProfileOwnerRequirement implements AuthorizationRequirement
{
    public function describe() : string { return 'errors.policies.owner.profile'; }
}
