<?php
declare(strict_types=1);

namespace App\Models\Errors;

use JsonSerializable;

readonly class Error implements JsonSerializable
{
    public function __construct(
        private string $code,
        private string $message
    ){}

    public function getCode() { return $this->code; }
    public function getMessage() { return $this->message; }

    public function jsonSerialize(): mixed
    {
        return ['code' => $this->code, 'message' => $this->message];
    }
}