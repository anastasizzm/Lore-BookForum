<?php
declare(strict_types=1);

namespace App\Extensions;

use App\Exceptions\ValidationException;
use App\Models\Files\UploadedFile;

final class UploadedFiles
{
    public static function one(array $files, string $field): ?UploadedFile
    {
        $uploads = $files[$field] ?? null;

        if (!is_array($uploads) || $uploads === []) {
            return null;
        }

        $first = $uploads[0] ?? null;

        if (!is_array($first)) {
            return null;
        }

        if (($first['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return UploadedFile::fromArray($first);
    }

    public static function many(array $files, string $field): array
    {
        $uploads = $files[$field] ?? null;

        if (!is_array($uploads) || $uploads === []) {
            return [];
        }

        $result = [];

        foreach ($uploads as $upload) {
            if (!is_array($upload)) {
                continue;
            }

            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result[] = UploadedFile::fromArray($upload);
        }

        return $result;
    }
}