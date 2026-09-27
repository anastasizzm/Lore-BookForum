<?php
declare(strict_types=1);

namespace App\Http;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\HttpContext;
use App\Http\Route;
use App\Http\RouteRegistry;

use RuntimeException;

use App\Lib\Container;
use App\Lib\Auth\AuthPolicy;

final class Router
{
    private readonly RouteRegistry $registry;

    public function __construct(
        private readonly Container $container
    ) {
        $this->registry = $container->get(RouteRegistry::class);
        if (!isset($this->registry))
            throw new RuntimeException('RouteRegistry is not provided to container');
    }

    public function get(string $path, callable|array $handler, ?string $name = null, AuthPolicy|string|array $authPolicy = AuthPolicy::Public): void
    { $this->add('GET', $path, $handler, $name, $authPolicy); }

    public function post(string $path, callable|array $handler, ?string $name = null, AuthPolicy|string|array $authPolicy = AuthPolicy::Public): void
    { $this->add('POST', $path, $handler, $name, $authPolicy); }

    public function put(string $path, callable|array $handler, ?string $name = null, AuthPolicy|string|array $authPolicy = AuthPolicy::Public): void
    { $this->add('PUT', $path, $handler, $name, $authPolicy); }

    public function patch(string $path, callable|array $handler, ?string $name = null, AuthPolicy|string|array $authPolicy = AuthPolicy::Public): void
    { $this->add('PATCH', $path, $handler, $name, $authPolicy); }

    public function delete(string $path, callable|array $handler, ?string $name = null, AuthPolicy|string|array $authPolicy = AuthPolicy::Public): void
    { $this->add('DELETE', $path, $handler, $name, $authPolicy); }

    private function add(
        string $method,
        string $path,
        callable|array $handler,
        ?string $name,
        AuthPolicy|string|array $authPolicy,
    ): void {
        $this->registry->add(new Route(
            method: strtoupper($method),
            path: $path,
            regex: $this->compile($path),
            handler: $handler,
            name: $name,
           authPolicy: $authPolicy
        ));
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

        foreach ($this->registry->routes() as $method => $routes) {
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
    public function execute(HttpContext $ctx): Response
    {
        $handler = $ctx->route->handler;
        $params = array_values($ctx->routeParams);

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = $this->container->get($class);
            $result = $controller->$method($ctx, ...$params);
        } else {
            $result = $handler($ctx, ...$params);
        }

        return $result instanceof Response ? $result : Response::json($result);
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