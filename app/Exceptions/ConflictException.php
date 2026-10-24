<?php
declare(strict_types=1);

namespace App\Exceptions;

use App\Http\HttpException;

final class ConflictException extends HttpException
{
    public function __construct(
        string $message,
        string $errorCode = "conflict"
    ){
        parent::__construct($message, 409, $errorCode);
    }
}