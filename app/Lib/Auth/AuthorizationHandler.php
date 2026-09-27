<?php
declare(strict_types=1);

namespace App\Lib\Auth;

use App\Http\HttpContext;
use App\Lib\Auth\Decision;
use App\Lib\Auth\AuthorizationRequirement;

interface AuthorizationHandler
{
    public function handle(AuthorizationRequirement $requirement, HttpContext $ctx): Decision;
}