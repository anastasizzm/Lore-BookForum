<?php
declare(strict_types=1);

namespace App\Extensions\Parsers;

use App\Exceptions\NotFoundException;
use App\ErrorCodes;

final class RouteParamParser extends BaseInputParser
{
    protected static function fail(string $key, string $message): never
    {
        throw new NotFoundException('Not Found', ErrorCodes::NOT_FOUND);
    }
}