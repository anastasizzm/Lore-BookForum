<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Http\Middleware;
use App\Http\UrlGenerator;
use App\Http\Router;

use App\Lib\Jwt;
use App\Constants;
use App\Lib\View;

final class AuthMiddleware implements Middleware
{
    public function __construct(
        private readonly Jwt $jwt,
        private readonly UrlGenerator $url
    ){}

    public function handle(Request $request, callable $next) : Response
    {
        $token = $request->getCookie(Constants::TOKEN_COOKIE, '');
        $claims = $this->jwt->decodeAccess($token);

        if ($claims === null) return $request->isApi() 
            ? Response::json(["errors" => ["Invalid token"]], 401)
            : Response::redirect($this->url->url('login'));

        $request->setAttribute(Constants::USER_ID_ATTR, $claims['sub']);
        $request->setAttribute(Constants::USERNAME_ATTR, $claims['username']);
        
        View::share(Constants::CSRF_ATTR, $claims['csrf']);

        return $next($request);
    }
}