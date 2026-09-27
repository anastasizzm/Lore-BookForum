<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\Middleware;
use App\Http\UrlGenerator;
use App\Http\Router;

use App\Lib\Jwt;
use App\Lib\View;

use App\Constants;
use App\ErrorCodes;

final class JwtMiddleware implements Middleware
{
    public function __construct(
        private readonly Jwt $jwt,
        private readonly UrlGenerator $url
    ){}

    public function handle(HttpContext $ctx, callable $next) : Response
    {
        $token = $ctx->cookie(Constants::TOKEN_COOKIE, '');
        $claims = $this->jwt->decodeAccess($token);

        if ($claims !== null){
            $ctx->request->setAttribute(Constants::USER_ID_ATTR, (int)$claims['sub']);
            $ctx->request->setAttribute(Constants::VERIFIED_ATTR, $claims['verified'] === '1');
        } 

        return $next($ctx);
    }
}