<?php
declare(strict_types=1);

namespace App\Http\Extensions\Parsers;

use App\Exceptions\ValidationException;
use App\Extensions\EnumExtensions;
use BackedEnum;
use UnitEnum;

final class QueryParser extends BaseInputParser
{
    protected static function fail(string $key, string $message): never
    {
        throw new ValidationException([$key => [$message]]);
    }
}