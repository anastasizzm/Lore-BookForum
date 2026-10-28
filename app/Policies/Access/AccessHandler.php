<?php
declare(strict_types=1);

namespace App\Policies\Access;

use App\Http\HttpContext;
use App\Services\Access\AccessService;

use App\ErrorCodes;
use App\Constants;

use App\Policies\Access\AccessRequirement;

use App\Lib\Auth\AuthorizationHandler;
use App\Lib\Auth\Decision;
use App\Lib\Auth\AuthorizationRequirement;
use App\Lib\Container;


final class AccessHandler implements AuthorizationHandler
{
    public function __construct(
        private readonly Container $container
    ){}

    public function handle(AuthorizationRequirement $requirement, HttpContext $ctx): Decision
    {  
        if (!$requirement instanceof AccessRequirement)
            return Decision::forbidden(ErrorCodes::INVALID_REQUIREMENT, 'Invalid requirement used');

        $currentUserId = $ctx->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Decision::unauthorized(ErrorCodes::UNAUTHORIZED, 'errors.policies.auth');

        if(!$ctx->attribute(Constants::VERIFIED_ATTR))
            return Decision::forbidden(ErrorCodes::FORBIDDEN, $requirement->describe());

        $service = $this->container->getKeyed($requirement->getTypeKey(), AccessService::class);

        $pathMatch = preg_match($requirement->pathPattern, $ctx->request->path, $m);
        $entityId = count($m) > 1 ? $m[1] : NULL;

        if ($service->hasAccess($currentUserId, $entityId, $requirement->operation))
            return Decision::allow();
        else return Decision::forbidden(ErrorCodes::FORBIDDEN, $requirement->describe());
    }
}