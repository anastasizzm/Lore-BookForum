<?php
declare(strict_types=1);

namespace App\Cache\Auth;

use App\Cache\UserContextCache;
use App\Models\Users\UserContext;
use App\Lib\Data\RedisClient;

final class RedisTokenResetTtlCache implements TokenResetTtlCache
{
    private const string PREFIX = 'token_reset:';

    public function __construct(private readonly RedisClient $redis) {}

    public function get(int $userId): ?int
    {
        $ttl = $this->redis->get(self::PREFIX . $userId);
        if($ttl === null) return NULL;

        return (int)$ttl;
    }

    public function set(int $userId, int $timeStamp, int $ttl = 300): void
    {
        $this->redis->setex(self::PREFIX . $userId, $ttl, (string)$timeStamp);
    }

    public function forget(int $userId): void
    {
        $this->redis->del(self::PREFIX . $userId);
    }
}