<?php
declare(strict_types=1);

namespace App\Policies\Auth;

use App\Http\HttpContext;

use App\ErrorCodes;
use App\Constants;

use App\Lib\Auth\AuthorizationHandler;
use App\Lib\Auth\Decision;
use App\Lib\Auth\AuthorizationRequirement;

use App\Policies\Auth\AuthenticatedRequirement;

final class AuthenticatedHandler implements AuthorizationHandler
{
    public function handle(AuthorizationRequirement $requirement, HttpContext $ctx): Decision
    {
        if (!$requirement instanceof AuthenticatedRequirement)
            return Decision::forbidden(ErrorCodes::INVALID_REQUIREMENT, 'Invalid requirement used');

        $userId = $ctx->attribute(Constants::USER_ID_ATTR);
        
        if (isset($userId)) return Decision::allow();
        else return Decision::unauthorized(ErrorCodes::FORBIDDEN, $requirement->describe());
    }
}