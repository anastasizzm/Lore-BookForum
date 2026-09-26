<?php
declare(strict_types=1);

namespace App\Lib;

final readonly class Cookie
{
    public function __construct(
        public string $name,
        public string $value,
        public int $maxAge = 0,        // seconds; 0 = session cookie
        public string $path = '/',
        public bool $secure = true,
        public bool $httpOnly = true,
        public string $sameSite = 'Lax', // 'Strict' | 'Lax' | 'None'
    ) {}

    public function toHeader(): string
    {
        $parts = [
            rawurlencode($this->name) . '=' . rawurlencode($this->value),
            'Path=' . $this->path,
            'SameSite=' . $this->sameSite,
        ];

        if ($this->maxAge > 0) {
            $parts[] = 'Max-Age=' . $this->maxAge;
        }
        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($this->secure) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    public function expired(): self
    {
        return new self(
            name:     $this->name,
            value:    '',
            maxAge:   0,
            path:     $this->path,
            secure:   $this->secure,
            httpOnly: $this->httpOnly,
            sameSite: $this->sameSite,
        );
    }
}