<?php
declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;

final class Pipeline
{
    /** @var Middleware[] */
    private array $middleware = [];

    public function __construct(private $destination) {}

    public function through(Middleware ...$middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function then(Request $request): Response
    {
        $next = fn(Request $r): Response => ($this->destination)($r);

        foreach (array_reverse($this->middleware) as $mw) {
            $prev = $next;
            $next = fn(Request $r): Response => $mw->handle($r, $prev);
        }

        return $next($request);
    }
}