<?php
declare(strict_types=1);

namespace App\Http;

final class RouteUrlGenerator implements UrlGenerator
{
    public function __construct(private readonly RouteRegistry $registry) {}

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

        return '/' . ltrim($path, '/');
    }
}