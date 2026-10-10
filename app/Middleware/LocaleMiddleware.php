<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\HttpContext;
use App\Http\Middleware;
use App\Http\Response;
use App\Lib\I18n\Translator;
use App\Services\Configuration\CookieService;

use App\Constants;

final class LocaleMiddleware implements Middleware
{
    public function __construct(
        private readonly Translator $translator,
        private readonly CookieService $cookies,
    ) {}

    public function handle(HttpContext $ctx, callable $next): Response
    {
        $locale = $this->resolveLocale($ctx);
        $this->translator->setLocale($locale);

        $ctx->request->setAttribute(Constants::LANG_ATTR, $locale);

        $response = $next($ctx);
        return $response;
    }

    private function resolveLocale(HttpContext $ctx): string
    {
        $available = $this->translator->available();

        $query = $ctx->query(Constants::LANG_QUERY);
        if (is_string($query) && in_array($query, $available, true)) {
            return $query;
        }

        $cookie = $ctx->cookie(Constants::LANG_COOKIE);
        if (is_string($cookie) && in_array($cookie, $available, true)) {
            return $cookie;
        }

        $accept = $ctx->header(Constants::LANG_HEADER);
        if (is_string($accept)) {
            foreach ($this->parseAcceptLanguage($accept) as $lang) {
                if (in_array($lang, $available, true)) {
                    return $lang;
                }
            }
        }

        return $this->translator->fallback();
    }

    /** @return list<string> */
    private function parseAcceptLanguage(string $header): array
    {
        $langs = [];

        foreach (explode(',', $header) as $part) {
            $lang = strtolower(explode(';', trim($part))[0]);
            $lang = explode('-', $lang)[0];

            if ($lang !== '') {
                $langs[] = $lang;
            }
        }

        return array_values(array_unique($langs));
    }
}