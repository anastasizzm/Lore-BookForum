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

    public function process(HttpContext $ctx, callable $handler) : Response
    {
        $next = $handler;

        foreach (array_reverse($this->middleware) as $mw) {
            $prev = $next;
            $next = fn(HttpContext $r): Response => $mw->handle($r, $prev);
        }

        return $next($ctx);
    }
}