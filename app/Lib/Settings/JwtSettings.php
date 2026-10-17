<?php
declare(strict_types=1);

namespace App\Lib\Settings;

final readonly class JwtSettings 
{
    private const string DATA_KEY = 'jwt';

    public function __construct(
        public string $issuer,
        public string $secret,
        public int $accessTtl,
        public int $refreshTtl
    ){}

    public static function fromData(array $data) : self
    {
        $jwt = $data[self::DATA_KEY] ?? [];
        return new self(
            issuer: $jwt['issuer'] ?? 'myapp',
            secret: $jwt['secret'],
            accessTtl: $jwt['access_ttl']  ?? 3600,
            refreshTtl: $jwt['refresh_ttl']  ?? 60 * 60 * 24 * 30
        );
    }
}