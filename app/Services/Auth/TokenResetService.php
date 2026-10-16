<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Cache\Auth\TokenResetTtlCache;
use App\Lib\Settings\Settings;

final class TokenResetService 
{
    public function __construct(
        private readonly TokenResetTtlCache $tokenCache,
        private readonly Settings $settings
    ){}

    public function resetFromUser(int $userId)
    {
        $this->tokenCache->set($userId, time(), $this->resetTtl());
    }

    public function isValid(int $userId, int $tokenIat) : bool
    {
        $ttl = $this->tokenCache->get($userId);
        if ($ttl === NULL || (int)$ttl < $tokenIat)
            return true;

        return false;
    }

    private function resetTtl() : int
    {
        return $this->settings->jwt->accessTtl * 3;
    }
}