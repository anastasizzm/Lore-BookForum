<?php
declare(strict_types=1);

namespace App\Lib;

final class Route
{
    /**
     * @param class-string[] $skipMiddleware
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $regex,
        public readonly mixed $handler,
        public readonly ?string $name = null,
        public readonly array $skipMiddleware = [],
    ) {}

    /** Does this route want to skip the given middleware class? */
    public function shouldSkip(string $middlewareClass): bool
    {
        return in_array($middlewareClass, $this->skipMiddleware, true);
    }
}