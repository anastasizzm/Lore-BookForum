<?php
declare(strict_types=1);

namespace App\Cache\RuleProviders;

interface RuleProviderCache
{
    /** @return array<string, mixed>|null */
    public function get(string $key): ?array;

    /** @param array<string, mixed> $data */
    public function set(string $key, array $data, int $ttl = 300): void;

    public function delete(string $key): void;

    public function deleteByPrefix(string $prefix): void;
}