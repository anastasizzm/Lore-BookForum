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

final class AuthMiddleware implements Middleware
{
    public function __construct(
        private readonly Jwt $jwt,
        private readonly UrlGenerator $url
    ){}

    public function handle(HttpContext $ctx, callable $next) : Response
    {
        $token = $ctx->cookie(Constants::TOKEN_COOKIE, '');
        $claims = $this->jwt->decodeAccess($token);

        if ($claims === null) return $ctx->request->isApi() 
            ? Response::json([
                'errors' => [
                    [
                        'errorCode' => ErrorCodes::TOKEN_FAIL,
                        'message' => 'Authentication token failed'
                    ]
                ],
                'code'  => 401,
                'message' => 'Unauthorized',
            ], 401)
            : Response::redirect($this->url->url('login'));

        $ctx->request->setAttribute(Constants::USER_ID_ATTR, (int)$claims['sub']);
        $ctx->request->setAttribute(Constants::VERIFIED_ATTR, (int)$claims['verified']);
        
        return $next($ctx);
    }
}