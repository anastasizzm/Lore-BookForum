<?php
declare(strict_types=1);

namespace App\Lib\Data;

use App\Lib\Settings;
use Redis;
use RedisException;
use RuntimeException;

final class RedisClient
{
    private ?Redis $redis = null;

    public function __construct(private readonly Settings $settings) {}

    public function connection(): Redis
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        try {
            $redis = new Redis();
            $redis->connect(
                $this->settings->redisHost,
                $this->settings->redisPort,
                timeout: 1.0,
            );

            if ($this->settings->redisPassword !== '') {
                $redis->auth($this->settings->redisPassword);
            }

            if ($this->settings->redisDatabase > 0) {
                $redis->select($this->settings->redisDatabase);
            }

            return $this->redis = $redis;
        } catch (RedisException $e) {
            throw new RuntimeException('Redis connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function get(string $key): ?string
    {
        $value = $this->connection()->get($key);
        return is_string($value) ? $value : null;
    }

    public function setex(string $key, int $ttl, string $value): void
    {
        $this->connection()->setex($key, $ttl, $value);
    }

    public function del(string ...$keys): void
    {
        $this->connection()->del($keys);
    }

    public function close(): void
    {
        if ($this->redis !== null) {
            $this->redis->close();
            $this->redis = null;
        }
    }
}