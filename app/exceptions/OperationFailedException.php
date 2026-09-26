<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class OperationFailedException extends RuntimeException 
{
    public function __construct(public readonly string $title, string $message)
    {
        parent::__construct($message);
    }
}