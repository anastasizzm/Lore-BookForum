<?php
declare(strict_types=1);

namespace App\Policies\Admin;

use App\Http\HttpContext;

use App\ErrorCodes;
use App\Constants;

use App\Lib\Auth\AuthorizationHandler;
use App\Lib\Auth\Decision;
use App\Lib\Auth\AuthorizationRequirement;

use App\Policies\Verified\VerifiedRequirement;

final class VerifiedHandler implements AuthorizationHandler
{
    public function handle(AuthorizationRequirement $requirement, HttpContext $ctx): Decision
    {
        if (!$requirement instanceof VerifiedRequirement)
            return Decision::forbidden(ErrorCodes::INVALID_REQUIREMENT, 'Invalid requirement used');

        if ($ctx->attribute(Constants::VERIFIED_ATTR)) return Decision::allow();
        else return Decision::forbidden(ErrorCodes::FORBIDDEN, $requirement->describe());
    }
}