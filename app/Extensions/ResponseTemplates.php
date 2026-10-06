<?php
declare(strict_types=1);

namespace App\Extensions;

use App\Models\Errors\Error;

final class ResponseTemplates
{
    public static function error(Error $error)
    {
        return [
            'error' => $error
        ];
    }

    public static function list(array $items, array $meta)
    {
        return [
            'items' => $items,
            'meta' => $meta
        ];
    }

    public static function object(object $data)
    {
        return [
            'data' => $data,
        ];
    }
}