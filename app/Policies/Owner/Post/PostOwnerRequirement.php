<?php
declare(strict_types=1);

namespace App\Policies\Owner\Post;

use App\Lib\Auth\AuthorizationRequirement;

final class PostOwnerRequirement implements AuthorizationRequirement
{
    public function describe() : string { return 'errors.policies.owner.post'; }
}
