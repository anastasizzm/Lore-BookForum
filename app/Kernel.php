<?php
declare(strict_types=1);

namespace App;

use App\Http\HttpException;
use App\Lib\Settings;
use App\Http\Router;
use App\Lib\Container;
use App\Http\Pipeline;
use App\Http\Request;
use App\Http\Response;
use App\Http\HttpContext;

final class Kernel
{
    public function __construct(
        private Router $router,
        private Container $container,
        private readonly Settings $settings,
    ) 
    {}

    public function handle(Request $request): Response
    {
        $match = $this->router->match($request);
        if ($match === null) {
            throw new HttpException('Not Found', 404);
        }

        [$route, $params] = $match;

        $ctx = new HttpContext($request, $route, $params);
        $pipeline = new Pipeline();

        foreach ($this->settings->middleware as $middlewareClass) {
            if (!class_exists($middlewareClass))
                throw new \RuntimeException("Middleware class not found: {$middlewareClass}");

            $pipeline->through($this->container->get($middlewareClass));
        }

        $endpoint = fn(HttpContext $context) => $this->router->execute($context);
        return $pipeline->process($ctx, $endpoint);
    }
}