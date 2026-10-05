<?php
declare(strict_types=1);

namespace App\Exceptions;

use App\Http\HttpException;

final class UnauthorizedException extends HttpException
{
    public function __construct(
        string $message,
        string $errorCode = "unauthorized"
    ){
        parent::__construct($message, 401, $errorCode);
    }
}