<?php
declare(strict_types=1);

namespace App\Extensions;

final class ResponseTemplates
{
    public static function errors(array $errors, int $statusCode, string $message)
    {
        return [
            'errors' => $errors,
            'code' => $statusCode,
            'message' => $message
        ];
    }

    public static function list(array $items, array $meta)
    {
        return [
            'items' => $items,
            'meta' => $meta
        ];
    }

    public static function object(object $object, array $meta)
    {
        return [
            'content' => $object,
            'meta' => $meta
        ];
    }
}