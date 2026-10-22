<?php
declare(strict_types=1);

namespace App\Repositories\RuleProviders\Concrete;

use App\Models\Access\Rules\OperationChecker;
use App\Cache\RuleProviders\RuleProviderCache;

final class RuleProviderCachingProxy implements RuleProvider
{
    public function __construct(
        private readonly RuleProvider $inner,
        private readonly RuleProviderCache $cache,
        private readonly string $type,
        private readonly string $checkerClass,
    ) {}

    public function getRule(
        int $userId,
        int|string $entityId,
    ): OperationChecker {
        $key = $this->buildKey($userId, $entityId);

        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $this->checkerClass::fromArray($cached);
        }

        $checker = $this->inner->getRule($userId, $entityId);

        $this->cache->set($key, $checker->toArray());

        return $checker;
    }

    public function getRulesMany(
        int $userId,
        array $entities
    ) : array {
        $key = $this->buildKeyMany($userId, $entities);

        $cached = $this->cache->get($key);

        if ($cached !== null)
            return array_map(
                fn(array $rawData) => $this->checkerClass::fromArray($rawData), 
                $cached['data']
            );

        $checkers = $this->inner->getRulesMany($userId, $entities);
        $checkers = array_map(
            fn(OperationChecker $checker) => $checker->toArray(),
            $checkers
        );
        $this->cache->set($key, ['data' => $checkers]);
        return $checker;
    }

    /** @param array<int|string> $entities */
    private function buildKeyMany(int $userId, array $entities) : string 
    {
        $normalized = array_map(fn(int|string $id) => $this->normalizeId($id), $entities);
        $imploded = implode('-', $normalized);
        return "concrete_rule_m:{$this->type}:{$userId}:{$imploded}";
    }

    private function buildKey(int $userId, int|string $entityId): string
    {
        $id = $this->normalizeId($entityId);

        return "concrete_rule:{$this->type}:{$userId}:{$id}";
    }

    private function normalizeId(int|string $entityId): string
    {
        if (is_int($entityId)) {
            return (string) $entityId;
        }

        if (is_string($entityId)) {
            return $entityId;
        }

        throw new \InvalidArgumentException(
            'Entity must be int or string: '
            . get_debug_type($entityId)
        );
    }
}