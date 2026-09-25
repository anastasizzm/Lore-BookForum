<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Http\Middleware;
use App\Lib\Jwt;
use App\Http\Router;
use App\Constants;

final class AuthMiddleware implements Middleware
{
    public function __construct(
        private readonly Jwt $jwt,
        private readonly Router $router
    ){}

    public function handle(Request $request, callable $next) : Response
    {
        $token = $request->getCookie(Constants::TOKEN_COOKIE, '');
        $claims = $this->jwt->decodeAccess($token);

        if ($claims === null) return $request->isApi() 
            ? Response::json(["errors" => ["Invalid token"]], 401)
            : Response::redirect($this->router->url('login'));

        $request->setAttribute(Constants::CSRF_ATTR, $claims['csrf']);
        $request->setAttribute(Constants::USER_ID_ATTR, $claims['sub']);
        $request->setAttribute(Constants::USERNAME_ATTR, $claims['username']);

        return $next($request);
    }
}