<?php
declare(strict_types=1);

namespace App\Lib\Settings;

final readonly class RedisSettings 
{
    public function __construct(
        public string $host,
        public int $port,
        public string $password,
        public int $database,
        public int $ttl
    ){}
}