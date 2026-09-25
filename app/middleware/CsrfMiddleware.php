<?php
declare(strinct_types=1);

namespace App\Middleware;

use App\Lib\CsrfManager;
use App\Http\HttpException;

class CsrfMiddleware
{
    public function handle($request, $next)
    {
        // Only check "unsafe" methods
        if (in_array($request->method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');
            
            if (!$token || !CsrfManager::isValid($token)) {
                throw new HttpException('CSRF token mismatch', 403);
            }
        }
        return $next($request);
    }
}