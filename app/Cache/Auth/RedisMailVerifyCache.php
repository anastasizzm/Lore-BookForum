<?php
declare(strict_types=1);

namespace App\Cache\Auth;

use App\Lib\Data\RedisClient;

final class RedisMailVerifyCache implements MailVerifyCache
{
    private const string PREFIX = 'mail_verify:';

    public function __construct(private readonly RedisClient $redis) {}

    public function get(int $userId): ?string
    {
        return $this->redis->get(self::PREFIX . $userId);
    }

    public function set(int $userId, string $token, int $ttl = 300): void
    {

        $this->redis->setex(self::PREFIX . $userId, $ttl, $token);
    }

    public function forget(int $userId): void
    {
        $this->redis->del(self::PREFIX . $userId);
    }
}