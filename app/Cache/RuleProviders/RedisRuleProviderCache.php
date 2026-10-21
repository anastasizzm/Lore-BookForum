<?php
declare(strict_types=1);

namespace App\Cache\RuleProviders;

use App\Lib\Data\RedisClient;
use JsonException;

final class RedisRuleProviderCache implements RuleProviderCache
{
    public function __construct(
        private readonly RedisClient $redis,
    ) {}

    public function get(string $key): ?array
    {
        $raw = $this->redis->get($key);

        if ($raw === null) {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->redis->del($key);
            return null;
        }

        return is_array($data) ? $data : null;
    }

    public function set(string $key, array $data, int $ttl = 300): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $this->redis->setex($key, $ttl, $json);
    }

    public function delete(string $key): void
    {
        $this->redis->del($key);
    }

    public function deleteByPrefix(string $prefix): void
    {
        $cursor = '0';
        $pattern = $prefix . '*';

        do {
            $keys = $this->redis->connection()->scan($cursor, [
                'match' => $pattern,
                'count' => 100,
            ]);

            if (is_array($keys) && $keys !== []) {
                $this->redis->del(...$keys);
            }
        } while ($cursor !== 0 && $cursor !== '0');
    }
}