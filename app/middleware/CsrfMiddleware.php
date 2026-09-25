<?php
declare(strinct_types=1);

namespace App\Middleware;

use App\Lib\CsrfManager;
use App\Http\HttpException;

final class CsrfMiddleware implements Middleware
{
    public const CSRF_ATTR = 'csrf';

    /** Methods that change state and must be protected. */
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method, self::PROTECTED_METHODS, true)) {
            return $next($request);
        }
        
        $expected = $request->attribute(CSRF_ATTR);

        if (is_string($expected) && CsrfManager::verify($request, $expected)){
            return $next($request);
        }
            
        return $this->reject($request);
    }

    private function reject(Request $request): Response
    {
        $accept = $request->getHeader('accept', '');

        if (is_string($accept) && str_contains($accept, 'application/json')) {
            return Response::json(['errors' => ['CSRF token mismatch']], 419);
        }

        return Response::html(
            '<h1>419 — CSRF token mismatch</h1><p>Please reload the page and try again.</p>',
            419
        );
    }
}