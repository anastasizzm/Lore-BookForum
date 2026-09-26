<?php
declare(strict_types=1);

namespace App\Http;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getExtra(): array
    {
        return $this->extra;
    }
}