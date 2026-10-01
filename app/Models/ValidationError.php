<?php
declare(strict_types=1);

namespace App\Models;

use App\ErrorCodes;

readonly class ValidationError extends Error
{
    public function __construct(
        public string $element,
        array $messages
    ){
        parent::__construct(ErrorCodes::VALIDATION_FAIL, $messages);
    }
}