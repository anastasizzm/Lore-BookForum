<?php
declare(strict_types=1);

namespace App\Policies\Owner\Profile;

use App\Http\HttpContext;

use App\Services\Users\UsersService;

use App\ErrorCodes;
use App\Constants;

use App\Lib\Auth\AuthorizationHandler;
use App\Lib\Auth\Decision;
use App\Lib\Auth\AuthorizationRequirement;

use App\Policies\Owner\Profile\ProfileOwnerRequirement;

final class ProfileOwnerHandler implements AuthorizationHandler
{
    public function __construct(
        private readonly UsersService $usersService
    ){}

    private const USERS_PATTERN = "#users[/\\\\](\d+)#";

    public function handle(AuthorizationRequirement $requirement, HttpContext $ctx): Decision
    {
        if (!$requirement instanceof ProfileOwnerRequirement)
            return Decision::forbidden(ErrorCodes::INVALID_REQUIREMENT, 'Invalid requirement used');

        $currentUserId = $ctx->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Decision::unauthorized(ErrorCodes::UNAUTH_TRY);

        $context = $this->usersService->loadContext($currentUserId);
        if ($context === null) return Decision::unauthorized(ErrorCodes::UNAUTH_TRY);

        $pathMatch = preg_match(self::USERS_PATTERN, $ctx->request->path, $m);
        if (empty($pathMatch))
            return Decision::notFound(ErrorCodes::PATH_FAIL, "User was not found in path");

        if ($context->isAdmin || $m[1] == "$currentUserId")
            return Decision::allow();
        else return Decision::forbidden(ErrorCodes::FORBIDDEN, $requirement->describe());
    }
}