<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Constants;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\Middleware;

use App\Lib\View;

final class ViewGlobalsMiddleware implements Middleware
{
    public function handle(HttpContext $ctx, callable $next): Response
    {
        $csrf = $ctx->attribute(Constants::CSRF_ATTR);
        $userId = $ctx->attribute(Constants::USER_ID_ATTR);
        $isVerified = $ctx->attribute(Constants::VERIFIED_ATTR);

        View::share('currentUserId', $userId ?? null);
        View::share(Constants::CSRF_ATTR, $csrf ?? '');
        View::share('is_verified', $isVerified ?? false);

        return $next($ctx);
    }
}