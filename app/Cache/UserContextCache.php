<?php
declare(strict_types=1);

namespace App\Cache;

use App\Models\Users\UserContext;

interface UserContextCache
{
    public function get(int $userId): ?UserContext;

    public function set(UserContext $user, int $ttl = 300): void;

    public function forget(int $userId): void;
}