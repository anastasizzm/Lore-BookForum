<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Http\HttpContext;

use App\Lib\Container;
use App\Lib\Auth\PolicyRegistry;
use App\Lib\Auth\Decision;
use App\Lib\Auth\AuthorizationHandler;


final class AuthorizationService
{
    public function __construct(
        private readonly PolicyRegistry $registry,
        private readonly Container $container,
    ) {}

    /**
     * @param string|list<string> $policy
     */
    public function authorize(AuthPolicy|string|array $policy, HttpContext $ctx): Decision
    {
        foreach ((array) $policy as $item) {
            if ($item instanceof AuthPolicy && $item === AuthPolicy::Public) {
                continue;
            }

            [$requirement, $handlerClass] = $this->registry->resolve($item, $ctx);

            /** @var Handler $handler */
            $handler = $this->container->get($handlerClass);

            $decision = $handler->handle($requirement, $ctx);

            if (!$decision->allowed) {
                return $decision;
            }
        }

        return Decision::allow();
    }
}