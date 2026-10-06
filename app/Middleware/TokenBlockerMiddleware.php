<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Cache\Auth\TokenResetTtlCache;

use App\Extensions\ResponseTemplates;
use App\Models\Errors\Error;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\Middleware;
use App\Http\UrlGenerator;
use App\Http\Router;

use App\Lib\Jwt;
use App\Lib\View;

use App\Constants;
use App\ErrorCodes;

final class TokenBlockerMiddleware implements Middleware
{
    public function __construct(
        private readonly TokenResetTtlCache $cache,
        private readonly UrlGenerator $url
    ){}

    public function handle(HttpContext $ctx, callable $next) : Response
    {
        $userId = $ctx->attribute(Constants::USER_ID_ATTR);
        $tokenIat = $ctx->attribute(Constants::TOKEN_IAT);
        if (empty($userId) || empty($tokenIat))
            return $next($ctx);

        $ttl = $cache->get($userId);
        if ($ttl === NULL || (int)$ttl > $tokenIat)
            return $next($ctx);

        if ($ctx->isApi())
            return Response::json(ResponseTemplates::error(new Error(ErrorCodes::UNAUTHORIZED, "Authenticate first")), 401);
        else
            return Response::redirect('login');
    }
}