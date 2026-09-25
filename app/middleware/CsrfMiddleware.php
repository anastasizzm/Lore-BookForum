<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Lib\CsrfManager;
use App\Http\HttpException;
use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Constants;

final class CsrfMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method, Constants::PROTECTED_METHODS, true)) {
            return $next($request);
        }
        
        $expected = $request->getAttribute(Constants::CSRF_ATTR);

        if (is_string($expected) && CsrfManager::verify($request, $expected)){
            return $next($request);
        }
            
        return $request->isApi()
            ? Response::json(['errors' => ['CSRF token mismatch']], 419)
            : Response::html('<h1>CSRF token mismatch</h1><p>Please reload the page and try again.</p>', 419);
    }
}