<?php
declare(strict_types=1);

namespace App\Http;

use App\Http\Route;
use App\Http\Request;

final readonly class HttpContext
{
    public function __construct(
        public Request $request,
        public Route $route,
        /** @var array<string, string> */
        public array $routeParams = [],
    ) {}

    public function withRequest(Request $request): self
    {
        return new self($request, $this->route, $this->routeParams);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request->input($key, $default);
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->request->getAttribute($key, $default);
    }

    public function cookie(string $name, ?string $default = null) : ?string
    {
        return $this->request->getCookie($name, $default);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->request->getHeader($name, $default);
    }
}