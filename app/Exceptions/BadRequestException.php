<?php
declare(strict_types=1);

namespace App\Exceptions;

use App\Http\HttpException;

final class BadRequestException extends HttpException
{
    public function __construct(
        string $message,
        string $errorCode = "bad_req"
    ){
        parent::__construct($message, 400, $errorCode);
    }
}