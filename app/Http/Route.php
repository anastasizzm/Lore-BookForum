<?php
declare(strict_types=1);

namespace App\Http;

use App\Lib\Auth\AuthPolicy;

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
        public readonly AuthPolicy|string|array $authPolicy = AuthPolicy::Public,
    ) {}
}