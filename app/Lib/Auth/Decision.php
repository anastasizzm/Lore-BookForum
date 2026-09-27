<?php
declare(strict_types=1);

namespace App\Auth;

final readonly class Decision
{
    private function __construct(
        public bool $allowed,
        public int $status = 200,
        public string $errorCode = 'None',
        public ?string $reason = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function unauthorized(string $errorCode, string $reason = 'Authentication required'): self
    {
        return new self(false, 401, $errorCode, $reason);
    }

    public static function forbidden(string $errorCode, string $reason = 'Access denied'): self
    {
        return new self(false, 403, $errorCode, $reason);
    }

    public static function notFound(string $errorCode, string $reason = 'Not found'): self
    {
        return new self(false, 404, $errorCode, $reason);
    }
}