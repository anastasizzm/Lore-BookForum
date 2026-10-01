<?php
declare(strict_types=1);

namespace App\Policies\Owner\Post;

use App\Lib\Auth\AuthorizationRequirement;

final class PostOwnerRequirement implements AuthorizationRequirement
{
    public function describe() : string { return 'The user should be either post owner or admin'; }
}
