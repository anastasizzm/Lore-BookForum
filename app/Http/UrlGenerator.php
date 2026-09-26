<?php
declare(strict_types=1);

namespace App\Http;

interface UrlGenerator
{
    /**
     * @param array<string, scalar> $params
     */
    public function url(string $name, array $params = []): string;
}