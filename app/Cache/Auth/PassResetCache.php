<?php
declare(strict_types=1);

namespace App\Cache\Auth;

use App\Models\Users\UserContext;

interface PassResetCache
{
    public function get(int $userId): ?string;

    public function set(int $userId, string $token, int $ttl = 300): void;

    public function forget(int $userId): void;
}