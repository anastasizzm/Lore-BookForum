<?php
declare(strict_types=1);

namespace App\Models\Errors;

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

    public static function asError(string $title, string $message) : self
    {
        return new self(InnerMessageType::Error, $title, $message);
    }

    public static function asWarning(string $title, string $message) : self
    {
        return new self(InnerMessageType::Warning, $title, $message);
    }

    public static function asSuccess(string $title, string $message) : self
    {
        return new self(InnerMessageType::Success, $title, $message);
    }

    public static function fromValidation(string $title, array $errors) : self 
    {
        return self::asError($title, implode(";\n", $errors));
    }
}