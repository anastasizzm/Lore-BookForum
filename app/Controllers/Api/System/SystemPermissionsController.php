<?php
declare(strict_types=1);

namespace App\Controllers\Api\System;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;
use App\Http\HttpException;
use App\Lib\I18n\Translator;

use App\Controllers\Controller;

use App\Models\Error;
use App\Models\Enums\ServiceType;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Attributes\Inject;

use App\Constants;
use App\ErrorCodes;
use App\Extensions\Parsers\RouteParamParser;


final class SystemPermissionsController extends Controller
{
    public function __construct(
        #[Inject(key: ServiceType::System)]
        private readonly CommonRuleProvider $ruleProvider,
        private readonly Translator $translator
    ){}

    public function getCommonRules(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $rules = $this->ruleProvider->getRule($userId);
        return $this->jsonObject($rules);
    }
}