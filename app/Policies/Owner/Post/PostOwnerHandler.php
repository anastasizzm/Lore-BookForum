<?php
declare(strict_types=1);

namespace App\Policies\Owner\Post;

use App\Http\HttpContext;

use App\Services\Users\UsersService;
use App\Services\Publications\PostsService;

use App\ErrorCodes;
use App\Constants;

use App\Lib\Auth\AuthorizationHandler;
use App\Lib\Auth\Decision;
use App\Lib\Auth\AuthorizationRequirement;

use App\Policies\Owner\Post\PostOwnerRequirement;

final class PostOwnerHandler implements AuthorizationHandler
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly PostsService $postsService
    ){}

    private const POSTS_PATTERN = "#posts[/\\\\](\d+)#";

    public function handle(AuthorizationRequirement $requirement, HttpContext $ctx): Decision
    {
        if (!$requirement instanceof PostOwnerRequirement)
            return Decision::forbidden(ErrorCodes::INVALID_REQUIREMENT, 'Invalid requirement used');

        $currentUserId = $ctx->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Decision::unauthorized(ErrorCodes::UNAUTH_TRY);

        $context = $this->usersService->loadContext($currentUserId);
        if ($context === null) return Decision::unauthorized(ErrorCodes::UNAUTH_TRY);

        $pathMatch = preg_match(self::POSTS_PATTERN, $ctx->request->path, $m);
        if (empty($pathMatch))
            return Decision::notFound(ErrorCodes::PATH_FAIL, "Post was not found in path");

        if ($context->isAdmin || $this->postsService->hasAccess($currentUserId, (int)$m[1]))
            return Decision::allow();
        else return Decision::forbidden(ErrorCodes::FORBIDDEN, $requirement->describe());
    }
}