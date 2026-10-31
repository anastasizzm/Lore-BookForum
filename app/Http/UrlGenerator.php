<?php
declare(strict_types=1);

namespace App\Http;

interface UrlGenerator
{
    /**
     * @param array<string, scalar> $params
     * @param array<string, scalar> $query
     */
    public function url(string $name, array $params = [], array $query = []): string;

    /**
     * @param array<string, scalar> $params
     * @param array<string, scalar> $query
     */
    public function fullUrl(string $name, array $params = [], array $query = []) : string;
}