<?php
declare(strict_types=1);

namespace App\Policies\Admin;

use App\Http\HttpContext;

use App\ErrorCodes;
use App\Constants;

use App\Lib\Auth\AuthorizationHandler;
use App\Lib\Auth\Decision;
use App\Lib\Auth\AuthorizationRequirement;

use App\Policies\Admin\AdminRequirement;

final class AdminHandler implements AuthorizationHandler
{
    public function __construct(
        private readonly UsersService $usersService
    ){}

    public function handle(AuthorizationRequirement $requirement, HttpContext $ctx): Decision
    {
        if (!$requirement instanceof AdminRequirement)
            return Decision::forbidden(ErrorCodes::INVALID_REQUIREMENT, 'Invalid requirement used');

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Decision::unauthorized(ErrorCodes::UNAUTH_TRY);

        $context = $usersService->loadContext($currentUserId);
        if ($context === null || !$context->isAdmin) return Decision::forbidden(ErrorCodes::FORBIDDEN, $requirement->describe());
        else return Decision::allow();
    }
}