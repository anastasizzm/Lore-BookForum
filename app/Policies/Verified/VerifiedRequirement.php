<?php
declare(strict_types=1);

namespace App\Policies\Verified;

use App\Lib\Auth\AuthorizationRequirement;

final class VerifiedRequirement implements AuthorizationRequirement
{
    public function describe() { return 'The user\'s email must be verified'; }
}
