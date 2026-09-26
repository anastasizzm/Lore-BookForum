<?php
declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class RouteRegistry
{
    /** @var array<string, Route[]> */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $named = [];

    public function add(Route $route): void
    {
        $this->routes[$route->method][] = $route;

        if ($route->name !== null) {
            $this->named[$route->name] = $route;
        }
    }

    /** @return array<string, Route[]> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function named(string $name): Route
    {
        return $this->named[$name]
            ?? throw new RuntimeException("Route named '$name' not found");
    }
}