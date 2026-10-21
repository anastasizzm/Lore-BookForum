<?php
declare(strict_types=1);

namespace App\Repositories\RuleProviders\Common;

use App\Models\Access\Rules\OperationChecker;
use App\Cache\RuleProviders\RuleProviderCache;

final class CommonRuleProviderCachingProxy implements CommonRuleProvider
{
    public function __construct(
        private readonly CommonRuleProvider $inner,
        private readonly RuleProviderCache $cache,
        private readonly string $type,
        private readonly string $checkerClass,
    ) {}

    public function getRule(int $userId): OperationChecker {
        $key = $this->buildKey($userId);

        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $this->checkerClass::fromArray($cached);
        }

        $checker = $this->inner->getRule($userId);

        $this->cache->set($key, $checker->toArray());

        return $checker;
    }

    private function buildKey(int $userId): string
    {
        return "common_rule:{$this->type}:{$userId}";
    }
}