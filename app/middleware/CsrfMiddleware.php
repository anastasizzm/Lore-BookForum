<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Lib\CsrfManager;
use App\Http\HttpException;
use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Constants;

use App\Services\CookieService;

final class CsrfMiddleware implements Middleware
{
    public function __construct(private readonly CookieService $cookies) {}

    public function handle(Request $request, callable $next): Response
    {
        $csrf = $request->getCookie(Constants::CSRF_COOKIE);
        $isCsrfSet = is_string($csrf);
        
        if(in_array($request->method, Constants::PROTECTED_METHODS, true))
            {
            if (!$isCsrfSet || !CsrfManager::verify($request, $csrf))
                return $request->isApi()
                ? Response::json(['errors' => ['CSRF token mismatch']], 419)
                : Response::html('<h1>CSRF token mismatch</h1><p>Please reload the page and try again.</p>', 419);
                
            View::share(Constants::CSRF_ATTR, $csrf);
            return $next($request);
        }
        else {
            if (!$isCsrfSet) $csrf = CsrfManager::generate();

            View::share(Constants::CSRF_ATTR, $csrf);
            $response = $next($request);

            if (!$isCsrfSet) $this->cookies->set($response, Constants::CSRF_COOKIE, $csrf);
            return $response;
        }
    }
}