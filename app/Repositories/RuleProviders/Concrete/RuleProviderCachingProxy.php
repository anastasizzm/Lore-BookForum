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
        int|string|object $entityId,
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

    private function buildKey(int $userId, int|string|object $entityId): string
    {
        $id = $this->normalizeId($entityId);

        return "concrete_rule:{$this->type}:{$userId}:{$id}";
    }

    private function normalizeId(int|string|object $entityId): string
    {
        if (is_int($entityId)) {
            return (string) $entityId;
        }

        if (is_string($entityId)) {
            return $entityId;
        }

        if (property_exists($entityId, 'id')) {
            return (string) $entityId->id;
        }

        if (method_exists($entityId, '__toString')) {
            return (string) $entityId;
        }

        throw new \InvalidArgumentException(
            'Entity must be int, string, or have id/__toString(): '
            . get_debug_type($entityId)
        );
    }
}