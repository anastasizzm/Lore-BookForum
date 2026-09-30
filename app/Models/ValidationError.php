<?php
declare(strict_types=1);

namespace App\Models;

use App\ErrorCodes;

readonly class ValidationError extends Error
{
    public function __construct(
        private string $element,
        private array $messages
    ){
        parent::__construct(ErrorCodes::VALIDATION_FAIL, "One or multiple problems occured during the validation");
    }

    public function element() : string { return $this->element; }
    public function messages() : array { return $this->messages; }
}