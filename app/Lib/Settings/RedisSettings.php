<?php
declare(strict_types=1);

namespace App\Lib\Settings;

final readonly class RedisSettings 
{
    private const string DATA_KEY = 'redis';

    public function __construct(
        public string $host,
        public int $port,
        public string $password,
        public int $database,
        public int $ttl
    ){}

    public static function fromData(array $data) : self
    {
        $redis = $data[self::DATA_KEY] ?? [];
        return new self(
            host: $redis['host'] ?? 'redis',
            port: (int)($redis['port'] ?? 6379),
            password: $redis['password'] ?? '',
            database: (int)($redis['database'] ?? 0),
            ttl: (int)($redis['ttl'] ?? 300)
        );
    }
}