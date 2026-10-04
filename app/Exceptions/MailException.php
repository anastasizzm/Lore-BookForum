<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

use App\Models\Errors\ToErrorConvertible;
use App\Models\Errors\Error;

final class MailException extends RuntimeException implements ToErrorConvertible
{
    public function __construct(
        string $message,
        string $errorCode = "mail_fail"
    )
    {
        parent::__construct($message);
    }

    public function toError() : Error
    {
        return new Error($this->errorCode, $this->message);
    }
}