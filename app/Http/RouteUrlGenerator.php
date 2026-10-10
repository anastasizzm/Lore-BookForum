<?php
declare(strict_types=1);

namespace App\Http;

use App\Lib\Settings\Settings;
use App\Lib\I18n\Translator;

final class RouteUrlGenerator implements UrlGenerator
{
    public function __construct(
        private readonly RouteRegistry $registry,
        private readonly Settings $settings,
        private readonly Translator $translator
    ) {}

    public function url(string $name, array $params = []): string
    {
        $route = $this->registry->named($name);
        $path  = $route->path;

        foreach ($params as $key => $value) {
            $path = str_replace(
                "{{$key}}",
                rawurlencode((string) $value),
                $path,
            );
        }

        return '/' . ltrim($path, '/') . '?lang=' . $this->translator->locale();
    }

    public function fullUrl(string $name, array $params = []) : string
    {
        $url = $this->url($name, $params);
        $host = trim($this->settings->appUrl, '/');
        return $host . $url;
    }
}