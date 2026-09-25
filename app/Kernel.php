<?php
declare(strict_types=1);

namespace App;

use App\Http\HttpException;
use App\Lib\Settings;
use App\Http\Router;
use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;

final class Kernel
{
    public function __construct(
        private Router $router,
        private Settings $settings
    ) {}

    public function handle(Request $request): Response
    {
        $match = $this->router->match($request);
        if ($match === null) {
            throw new HttpException('Not Found', 404);
        }

        [$route, $params] = $match;

        $endpoint = fn(Request $req) => $this->router->execute($route, $req, $params);
        $pipeline = new Pipeline();

        foreach ($this->settings->middleware as $middlewareClass) {
            if (class_exists($middlewareClass)) {
                $pipeline->through(new $middlewareClass());
            } else {
                throw new \RuntimeException("Middleware class not found: {$middlewareClass}");
            }
        }

        return $pipeline->process($request, $route, $endpoint);
    }
}