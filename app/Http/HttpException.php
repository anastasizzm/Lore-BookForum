<?php
declare(strict_types=1);

namespace App\Http;

use RuntimeException;
use App\Models\Errors\ToErrorConvertible;
use App\Models\Errors\Error;

class HttpException extends RuntimeException implements ToErrorConvertible
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly string $errorCode = "bad_request"
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode() : string 
    {
        return $this->errorCode;
    }

    public function toError() : Error 
    {
        return new Error($this->errorCode, $this->getMessage());
    }
}