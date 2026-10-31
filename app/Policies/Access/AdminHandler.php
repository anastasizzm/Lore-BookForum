<?php
declare(strict_types=1);

namespace App\Policies\Access;

use App\Http\HttpContext;
use App\Attributes\Inject;
use App\Services\Access\AccessService;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Models\Enums\ServiceType;

use App\ErrorCodes;
use App\Constants;

use App\Policies\Access\AdminRequirement;

use App\Lib\Auth\AuthorizationHandler;
use App\Lib\Auth\Decision;
use App\Lib\Auth\AuthorizationRequirement;
use App\Lib\Container;


final class AdminHandler implements AuthorizationHandler
{
    public function __construct(
        #[Inject(key: ServiceType::Publication)]
        private readonly CommonRuleProvider $pubProvider,

        #[Inject(key: ServiceType::Post)]
        private readonly CommonRuleProvider $postProvider,

        #[Inject(key: ServiceType::System)]
        private readonly CommonRuleProvider $sysProvider,
    ){}

    public function handle(AuthorizationRequirement $requirement, HttpContext $ctx): Decision
    {  
        if (!$requirement instanceof AdminRequirement)
            return Decision::forbidden(ErrorCodes::INVALID_REQUIREMENT, 'Invalid requirement used');

        $currentUserId = $ctx->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Decision::unauthorized(ErrorCodes::UNAUTHORIZED, 'errors.policies.auth');

        if(!$ctx->attribute(Constants::VERIFIED_ATTR))
            return Decision::forbidden(ErrorCodes::FORBIDDEN, $requirement->describe());

        $hasAccess = false;
        foreach([
            $this->pubProvider->getRule($currentUserId),
            $this->postProvider->getRule($currentUserId),
            $this->sysProvider->getRule($currentUserId),
        ] as $rule) $hasAccess |= $rule->hasAny();

        if ($hasAccess)
            return Decision::allow();
        else return Decision::forbidden(ErrorCodes::FORBIDDEN, $requirement->describe());
    }
}