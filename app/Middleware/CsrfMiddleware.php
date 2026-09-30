<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Lib\CsrfManager;
use App\Lib\View;

use App\Http\HttpException;
use App\Http\Middleware;
use App\Http\HttpContext;
use App\Http\Response;

use App\Constants;
use App\ErrorCodes;

use App\Extensions\ResponseTemplates;

use App\Models\Error;

use App\Services\Configuration\CookieService;

final class CsrfMiddleware implements Middleware
{
    public function __construct(private readonly CookieService $cookies) {}

    public function handle(HttpContext $ctx, callable $next): Response
    {
        $csrf = $ctx->cookie(Constants::CSRF_COOKIE);
        $isCsrfSet = is_string($csrf);
        
        if(in_array($ctx->request->method, Constants::PROTECTED_METHODS, true))
            {
            if (!$isCsrfSet || !CsrfManager::verify($ctx->request, $csrf))
                return $ctx->isApi()
                ? Response::json(ResponseTemplates::errors([
                    new Error(ErrorCodes::CSRF_FAIL, "CSRF token mismatch")
                ], 419, "CSRF mismatch"), 419)
                : Response::html('<h1>CSRF token mismatch</h1><p>Please reload the page and try again.</p>', 419);
                
            $ctx->request->setAttribute(Constants::CSRF_ATTR, $csrf);
            return $next($ctx);
        }
        else {
            if (!$isCsrfSet) $csrf = CsrfManager::generate();
            $ctx->request->setAttribute(Constants::CSRF_ATTR, $csrf);

            $response = $next($ctx);

            if (!$isCsrfSet) $response = $this->cookies->set($response, Constants::CSRF_COOKIE, $csrf, 0, false);
            return $response;
        }
    }
}