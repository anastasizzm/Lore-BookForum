<?php
declare(strict_types=1);

namespace App\Exceptions;

use App\Http\HttpException;

final class NotFoundException extends HttpException
{
    public function __construct(
        string $message,
        string $errorCode = "not_found"
    ){
        parent::__construct($message, 404, $errorCode);
    }
}