<?php
declare(strict_types=1);

namespace App\Lib\Auth;

use RuntimeException;

use App\Http\HttpContext;

use App\Lib\Auth\AuthorizationHandler;
use App\Lib\Auth\AuthPolicy;
use App\Lib\Auth\AuthorizationRequirement;

final class PolicyRegistry
{
    /** @var array<string, array{callable(HttpContext): AuthorizationRequirement, class-string<Handler>}> */
    private array $policies = [];

    private function register(
        string $policy,
        callable $requirementFactory,
        string $handlerClass,
    ): void {
        $this->policies[$policy] = [$requirementFactory, $handlerClass];
    }

    public function registerPolicy(
        AuthPolicy|string $policy,
        callable $requirementFactory,
        string $handlerClass,
    ): void {
        $this->register(
            $policy instanceof AuthPolicy ? $policy->value : $policy,
            $requirementFactory,
            $handlerClass,
        );
    }

    public function resolve(AuthPolicy|string $policy, HttpContext $ctx): array
    {
        $key = $policy instanceof AuthPolicy ? $policy->value : $policy;

        $entry = $this->policies[$key]
            ?? throw new RuntimeException("Policy '{$key}' is not registered");

        [$factory, $handlerClass] = $entry;

        return [$factory($ctx), $handlerClass];
    }

    public function has(string $policy): bool
    {
        return isset($this->policies[$policy]);
    }
}