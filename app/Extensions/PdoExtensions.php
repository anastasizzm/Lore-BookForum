<?php
declare(strict_types=1);

namespace App\Extensions;

final class PdoExtensions
{
    public static function extractConstraintName(string $message): ?string
    {
        if (preg_match('/constraint "([^"]+)"/', $message, $m)) {
            return $m[1];
        }

        return null;
    }
}