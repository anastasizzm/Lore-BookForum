<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Extensions\ResponseTemplates;
use App\Models\Errors\Error;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\Middleware;
use App\Http\UrlGenerator;
use App\Http\Router;

use App\Lib\Jwt;
use App\Lib\I18n\Translator;
use App\Lib\View;

use App\Services\Configuration\CookieService;
use App\Services\Auth\TokenResetService;

use App\Constants;
use App\ErrorCodes;

final class TokenBlockerMiddleware implements Middleware
{
    public function __construct(
        private readonly TokenResetService $service,
        private readonly CookieService $cookies,
        private readonly UrlGenerator $url,
        private readonly Translator $translator
    ){}

    public function handle(HttpContext $ctx, callable $next) : Response
    {
        $userId = $ctx->attribute(Constants::USER_ID_ATTR);
        $tokenIat = $ctx->attribute(Constants::TOKEN_IAT);
        if (empty($userId) || empty($tokenIat))
            return $next($ctx);

        if ($this->service->isValid($userId, $tokenIat))
            return $next($ctx);

        $response = $ctx->isApi()
            ? Response::json(ResponseTemplates::error(new Error(ErrorCodes::UNAUTHORIZED, $this->translator->t("errors.common.unauthorized"))), 401)
            : Response::redirect($this->url->url('login'));

        return $this->cookies->clear($response, Constants::TOKEN_COOKIE);
    }
}