<?php
declare(strict_types=1);

namespace App\Models;

readonly class Error
{
    public function __construct(
        public string $errorCode,
        public array $messages
    ){}

    public static function fromArray(string $errorCode, array $messages) : self 
    {
        return new self($errorCode, $messages);
    }

    public static function fromMessage(string $errorCode, string $message) : self 
    {
        return self::fromArray($errorCode, [$message]);
    }
}