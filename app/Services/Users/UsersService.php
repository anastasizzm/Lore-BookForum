<?php
declare(strict_types=1);

namespace App\Services\Users;

use App\Cache\UserContextCache;

use App\Models\Users\UserContext;
use App\Repositories\Users\UsersRepository;
use Throwable;

final class UsersService
{
    public function __construct(
        private readonly UserContextCache $cache,
        private readonly UsersRepository  $usersRepo,
    ){}

    public function loadContext(int $userId): ?UserContext
    {
        $user = null;

        try {
            $user = $this->cache->get($userId);
        } catch (Throwable $e) {
            error_log('[redis] user_ctx get failed: ' . $e->getMessage());
        }

        if ($user !== null) {
            return $user;
        }

        $user = $this->usersRepo->loadContext($userId);

        if ($user === null) {
            return null;
        }

        try {
            $this->cache->set($user, ttl: 300);
        } catch (Throwable $e) {
            error_log('[redis] user_ctx set failed: ' . $e->getMessage());
        }

        return $user;
    }

    public function forgetContext(int $userId): void
    {
        try {
            $this->cache->forget($userId);
        } catch (Throwable $e) {
            error_log('[redis] user_ctx forget failed: ' . $e->getMessage());
        }
    }
}