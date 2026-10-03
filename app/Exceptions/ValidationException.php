<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

use App\Models\Errors\ValidationError;
use App\Models\Errors\ToErrorConvertible;
use App\Models\Errors\Error;
use App\Models\Errors\InnerMessage;

final class ValidationException extends RuntimeException implements ToErrorConvertible
{
    public function __construct(
        private readonly array $errors = [],
        string $message = "The given data was invalid",
    ) {
        parent::__construct($message);
    }

    /** @return array<string, list<string>> */
    public function errors(): array { return $this->errors; }

    public function toError() : Error
    {
        return new ValidationError($this->errors, $this->message);
    }

    public function toMessages() : array 
    {
        $msgs = [];
        foreach($this->errors as $key => $fails)
            $msgs[] = InnerMessage::fromValidation($key, $fails);

        return $msgs;
    }
}