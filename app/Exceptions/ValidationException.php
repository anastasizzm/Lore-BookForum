<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

use App\Models\ValidationError;

final class ValidationException extends RuntimeException
{
    private array $errors;

    public function __construct(
        private readonly array $errorsArray = [],
        string $message = 'Validation failed',
    ) {
        parent::__construct($message);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        if (isset($this->errors)) return $this->errors;
        
        $this->errors = [];
        foreach($this->errorsArray as $element => $errors)
            $this->errors[] = new ValidationError($element, $errors);

        return $this->errors;
    }
}