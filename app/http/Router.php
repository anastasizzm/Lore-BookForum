<?php
declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<string, array<int, array{regex: string, handler: callable|array}>> */
    private array $routes = [];
    
    /** @var array<string, string> */
    private array $namedRoutes = [];

    public function get(string $path, callable|array $handler, ?string $name = null): void { $this->add('GET', $path, $handler, $name); }
    public function post(string $path, callable|array $handler, ?string $name = null): void { $this->add('POST', $path, $handler, $name); }
    public function put(string $path, callable|array $handler, ?string $name = null): void { $this->add('PUT', $path, $handler, $name); }
    public function patch(string $path, callable|array $handler, ?string $name = null): void { $this->add('PATCH', $path, $handler, $name); }
    public function delete(string $path, callable|array $handler, ?string $name = null): void { $this->add('DELETE', $path, $handler, $name); }

    private function add(string $method, string $path, callable|array $handler, ?string $name): void
    {
        $method = strtoupper($method);
        $regex = $this->compile($path);
        
        $this->routes[$method][] = [
            'regex'   => $regex,
            'handler' => $handler
        ];

        if ($name !== null) {
            $this->namedRoutes[$name] = $path;
        }
    }

    private function compile(string $path): string
    {
        $path = trim($path, '/');
        if ($path === '') return '#^/?$#';

        $pattern = preg_replace('#\{([a-zA-Z_]\w*)\}#', '(?P<$1>[^/]+)', $path);
        return '#^/' . $pattern . '/?$#';
    }

    /**
     * Reverse Routing: Generate a URL from a name and params
     * Example: $router->url('user.view', ['id' => 123]) -> "/user/123"
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RuntimeException("Route named '{$name}' not found.");
        }

        $path = $this->namedRoutes[$name];
        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", (string)$value, $path);
        }
        return $path;
    }

    public function dispatch(Request $request): Response
    {
        $allowedMethods = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $request->path, $matches)) {
                    if ($method === $request->method) {
                        return $this->execute($route['handler'], $request, $this->extractParams($matches));
                    }
                    $allowedMethods[] = $method;
                }
            }
        }

        if (!empty($allowedMethods)) {
            throw new HttpException('Method Not Allowed', 405);
        }

        throw new HttpException('Not Found', 404);
    }

    private function extractParams(array $matches): array
    {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function execute(callable|array $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $result = $controller->$method($request, ...array_values($params));
        } else {
            $result = $handler($request, ...array_values($params));
        }

        return ($result instanceof Response) ? $result : Response::json($result);
    }
}