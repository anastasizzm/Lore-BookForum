<?php
declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Route;

final class Pipeline
{
    /** @var Middleware[] */
    private array $middleware = [];

    public function through(Middleware ...$middlewares): self
    {
        foreach($middlewares as $middleware)
        {
            $this->middleware[] = $middleware;
        }
        return $this;
    }

    public function process(Request $request, Route $route, callable $endpoint) : Response
    {
        $next = fn(Request $r): Response => $endpoint($r);

        foreach (array_reverse($this->middleware) as $mw) {
            if ($route instanceof Route && $route->shouldSkip($mw::class)) {
                continue;
            }

            $prev = $next;
            $next = fn(Request $r): Response => $mw->handle($r, $prev);
        }

        return $next($request);
    }
}