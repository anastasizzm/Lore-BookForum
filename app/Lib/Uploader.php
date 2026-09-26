<?php
declare(strict_types=1);

namespace App\Lib;

final class Uploader
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public static function saveImage(array $file, string $subdir): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpException('Upload failed (error code ' . $error . ')', 422);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new HttpException('Invalid upload', 422);
        }

        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new HttpException('File too large (max ' . (self::MAX_BYTES / 1024 / 1024) . ' MB)', 422);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new HttpException('Unsupported image type', 422);
        }

        $dir = __DIR__ . '/../storage/uploads/' . trim($subdir, '/');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create upload directory: $dir");
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            throw new RuntimeException('Failed to move uploaded file');
        }

        return trim($subdir, '/') . '/' . $name;
    }

    /** Delete a previously saved file by relative path. Safe against traversal. */
    public static function delete(string $relativePath): void
    {
        if (str_contains($relativePath, '..')) return;
        $full = __DIR__ . '/../storage/uploads/' . ltrim($relativePath, '/');
        if (is_file($full)) @unlink($full);
    }
}