<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Lib\CsrfManager;
use App\Lib\View;
use App\Lib\I18n\Translator;

use App\Http\HttpException;
use App\Http\Middleware;
use App\Http\HttpContext;
use App\Http\Response;

use App\Constants;
use App\ErrorCodes;

use App\Extensions\ResponseTemplates;

use App\Models\Errors\Error;

use App\Services\Configuration\CookieService;

final class CsrfMiddleware implements Middleware
{
    private const CSRF_STATUS_CODE = 419;

    public function __construct(
        private readonly CookieService $cookies,
        private readonly Translator $translator
    ) {}

    public function handle(HttpContext $ctx, callable $next): Response
    {
        $csrf = $ctx->cookie(Constants::CSRF_COOKIE);
        $isCsrfSet = is_string($csrf);
        
        if(in_array($ctx->request->method, Constants::PROTECTED_METHODS, true))
        {
            if (!$isCsrfSet || !CsrfManager::verify($ctx->request, $csrf))
                return $ctx->isApi()
                ? Response::json(ResponseTemplates::error(new Error(ErrorCodes::CSRF_FAIL, $this->translator->t("errors.common.csrf_fail"))), self::CSRF_STATUS_CODE)
                : Response::html(View::render('message', ['message' => $this->translator->t("errors.common.csrf_fail"), 'statusCode' => self::CSRF_STATUS_CODE]), self::CSRF_STATUS_CODE);
                
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