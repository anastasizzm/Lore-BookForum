<?php
declare(strict_types=1);

namespace App\Models\Errors;

use App\Models\Errors\Error;

interface ToErrorConvertible 
{
    public function toError() : Error;
}