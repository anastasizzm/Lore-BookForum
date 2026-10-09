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
        if($ctx->request->isApi()) return $next($ctx);

        $csrf = $ctx->attribute(Constants::CSRF_ATTR);
        $userId = $ctx->attribute(Constants::USER_ID_ATTR);
        $isVerified = $ctx->attribute(Constants::VERIFIED_ATTR);
        $lang = $ctx->attribute(Constants::LANG_ATTR);

        View::share('currentUserId', $userId ?? null);
        View::share(Constants::CSRF_ATTR, $csrf ?? '');
        View::share('is_verified', $isVerified ?? false);
        View::share(Constants::LANG_ATTR, $lang ?? 'en');

        return $next($ctx);
    }
}