<?php
declare(strict_types=1);

namespace App\Models;

readonly class Error
{
    public function __construct(
        private string $errorCode,
        private string $message
    ){}

    public function code() : string { return $this->errorCode; }
    public function message() : string { return $this->message; }
}