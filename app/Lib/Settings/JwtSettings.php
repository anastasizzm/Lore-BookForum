<?php
declare(strict_types=1);

namespace App\Lib\Settings;

final readonly class JwtSettings 
{
    public function __construct(
        public string $issuer,
        public string $secret,
        public int $accessTtl,
        public int $refreshTtl
    ){}
}