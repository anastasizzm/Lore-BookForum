<?php
declare(strict_types=1);

namespace App\Models;

enum InnerMessageType : string
{
    case Error = 'error';
    case Warning = 'warning';
    case Success = 'success';
}

final readonly class InnerMessage
{
    public function __construct(
        public InnerMessageType $type,
        public string $title,
        public string $message
    ){}
}