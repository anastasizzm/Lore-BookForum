<?php
declare(strict_types=1);

namespace App\Http;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Lib\Route;
use RuntimeException;

final class Router
{
    /** @var array<string, Route[]> */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    public function get(string $path, callable|array $handler, ?string $name = null, array $skipMiddleware = []): void
    { $this->add('GET', $path, $handler, $name, $skipMiddleware); }

    public function post(string $path, callable|array $handler, ?string $name = null, array $skipMiddleware = []): void
    { $this->add('POST', $path, $handler, $name, $skipMiddleware); }

    public function put(string $path, callable|array $handler, ?string $name = null, array $skipMiddleware = []): void
    { $this->add('PUT', $path, $handler, $name, $skipMiddleware); }

    public function patch(string $path, callable|array $handler, ?string $name = null, array $skipMiddleware = []): void
    { $this->add('PATCH', $path, $handler, $name, $skipMiddleware); }

    public function delete(string $path, callable|array $handler, ?string $name = null, array $skipMiddleware = []): void
    { $this->add('DELETE', $path, $handler, $name, $skipMiddleware); }

    private function add(
        string $method,
        string $path,
        callable|array $handler,
        ?string $name,
        array $skipMiddleware,
    ): void {
        $route = new Route(
            method: strtoupper($method),
            path: $path,
            regex: $this->compile($path),
            handler: $handler,
            name: $name,
            skipMiddleware: $skipMiddleware,
        );

        $this->routes[$route->method][] = $route;

        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
        }
    }

    /**
     * Match the request against stored routes.
     *
     * @return array{0: Route, 1: array<string, string>}|null
     * @throws HttpException 405 if a path matches but no method does
     */
    public function match(Request $request): ?array
    {
        $pathMatched = false;

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                if (!preg_match($route->regex, $request->path, $matches)) {
                    continue;
                }

                if ($method === $request->method) {
                    return [$route, $this->extractParams($matches)];
                }

                $pathMatched = true;
            }
        }

        if ($pathMatched) {
            throw new HttpException('Method Not Allowed', 405);
        }

        return null;
    }

    /** Run the matched route's handler. */
    public function execute(Route $route, Request $request, array $params): Response
    {
        $handler = $route->handler;

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $result = (new $class())->$method($request, ...array_values($params));
        } else {
            $result = $handler($request, ...array_values($params));
        }

        return $result instanceof Response ? $result : Response::json($result);
    }

    public function url(string $name, array $params = []): string
    {
        $route = $this->namedRoutes[$name] ?? null;

        if ($route === null) {
            throw new RuntimeException("Route named '{$name}' not found.");
        }

        $path = $route->path;
        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", (string) $value, $path);
        }
        return $path;
    }

    private function compile(string $path): string
    {
        $path = trim($path, '/');
        if ($path === '') {
            return '#^/?$#';
        }

        $pattern = preg_replace('#\{([a-zA-Z_]\w*)\}#', '(?P<$1>[^/]+)', $path);
        return '#^/' . $pattern . '/?$#';
    }

    private function extractParams(array $matches): array
    {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}