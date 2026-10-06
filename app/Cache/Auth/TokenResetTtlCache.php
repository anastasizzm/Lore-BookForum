<?php
declare(strict_types=1);

namespace App\Cache\Auth;

interface TokenResetTtlCache
{
    public function get(int $userId): ?int;

    public function set(int $userId, int $timeStamp, int $ttl = 300): void;

    public function forget(int $userId): void;
}