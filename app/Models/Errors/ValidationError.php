<?php
declare(strict_types=1);

namespace App\Models\Errors;

use App\ErrorCodes;

readonly class ValidationError extends Error
{
    public function __construct(
        private array $details,
        string $message = "The given data was invalid"
    ){
        parent::__construct(ErrorCodes::VALIDATION_FAIL, $message);
    }

    public function getDetails() { return $this->details; }

    public function jsonSerialize(): mixed
    {
        $data = parent::jsonSerialize();
        $data['details'] = $this->details;
        return $data;
    }
}