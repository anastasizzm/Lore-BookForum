<?php
declare(strict_types=1);

namespace App\Cache;

use App\Cache\UserContextCache;
use App\Models\Users\UserContext;
use App\Lib\Data\RedisClient;

final class RedisUserContextCache implements UserContextCache
{
    private const string PREFIX = 'user_ctx:';

    public function __construct(private readonly RedisClient $redis) {}

    public function get(int $userId): ?UserContext
    {
        $raw = $this->redis->get(self::PREFIX . $userId);

        if ($raw === null) {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Битый JSON — считаем промахом и удаляем ключ
            $this->redis->del(self::PREFIX . $userId);
            return null;
        }

        return new UserContext(
            id:         (int) $data['id'],
            username:   (string) $data['username'],
            name:       (string) ($data['name'] ?? ''),
            avatar:     (string) ($data['avatar'] ?? 'default'),
            isAdmin:    (bool) ($data['is_admin'] ?? false),
            isRedactor: (bool) ($data['is_redactor'] ?? false),
        );
    }

    public function set(UserContext $user, int $ttl = 300): void
    {
        $payload = json_encode([
            'id'          => $user->id,
            'username'    => $user->username,
            'name'        => $user->name,
            'avatar'      => $user->avatar,
            'is_admin'    => $user->isAdmin,
            'is_redactor' => $user->isRedactor,
        ], JSON_THROW_ON_ERROR);

        $this->redis->setex(self::PREFIX . $user->id, $ttl, $payload);
    }

    public function forget(int $userId): void
    {
        $this->redis->del(self::PREFIX . $userId);
    }
}