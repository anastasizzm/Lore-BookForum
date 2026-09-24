<?php
declare(strict_types=1);

namespace App;

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
        $destination = fn(Request $req) => $this->router->dispatch($req);
        $pipeline = new Pipeline($destination);

        $middlewareInstances = [];
        foreach ($this->settings->middleware as $middlewareClass) {
            if (class_exists($middlewareClass)) {
                $middlewareInstances[] = new $middlewareClass();
            } else {
                throw new \RuntimeException("Middleware class not found: {$middlewareClass}");
            }
        }

        $pipeline->through(...$middlewareInstances);
        return $pipeline->then($request);
    }
}