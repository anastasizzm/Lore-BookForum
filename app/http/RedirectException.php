<?php
declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class RedirectException extends RuntimeException
{
    public function __construct(
        public readonly string $to,
        public readonly int $status = 302,
    ) {
        parent::__construct("Redirect to $to");
    }
}