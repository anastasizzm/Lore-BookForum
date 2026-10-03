<?php
declare(strict_types=1);

namespace App\Exceptions;

use App\Http\HttpException;

final class GoneException extends HttpException
{
    public function __construct(
        string $message,
        string $errorCode = "gone"
    ){
        parent::__construct($message, 410, $errorCode);
    }
}