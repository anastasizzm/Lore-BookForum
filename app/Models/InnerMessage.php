<?php
declare(strict_types=1);

namespace App\Models;

enum InnerMessageType
{
    case Error;
    case Warning;
    case Success;
}

final readonly class InnerMessage
{
    public function __construct(
        public InnerMessageType $type,
        public string $title,
        public string $message
    ){}
}