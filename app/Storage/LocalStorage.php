<?php
declare(strict_types=1);

namespace App\Storage;

use RuntimeException;
use App\Models\Files\StoredFile;
use App\Lib\Settings\StorageSettings;

final class LocalFileStorage implements FileStorage
{
    private const int PERMISSIONS = 0755;

    public function __construct(
        private readonly StorageSettings $settings
    ){
        if (!is_dir($settings->path))
        {
           if (!mkdir($settings->path, self::PERMISSIONS, true) && !is_dir($settings->path)) {
                throw new RuntimeException("Cannot create storage root: {$settings->path}");
            } 
        }
    }

    public function store(
        string $localPath,
        string $category,
        string $uuid,
        string $extension,
        string $mimeType,
    ): StoredFile {
        $relative = $this->buildPath($uuid, $extension);
        $absolute = $this->absolutePath($relative);

        if (!is_uploaded_file($localPath)) {
            if (!rename($localPath, $absolute)) {
                throw new RuntimeException("Cannot move file to: $absolute");
            }
        } else {
            if (!move_uploaded_file($localPath, $absolute)) {
                throw new RuntimeException("Cannot upload file to: $absolute");
            }
        }

        $size = filesize($absolute);

        if ($size === false) {
            throw new RuntimeException("Cannot stat file: $absolute");
        }

        return new StoredFile(
            path:     $relative,
            mimeType: $mimeType,
            size:     $size,
        );
    }

    public function delete(string $path): void
    {
        $absolute = $this->settings->path . '/' . $path;

        if (is_file($absolute)) {
            unlink($absolute);

            $this->cleanupEmptyDirs(dirname($absolute));
        }
    }

    public function exists(string $path): bool
    {
        return is_file($this->settings->path . '/' . $path);
    }

    public function absolutePath(string $path): string
    {
        return $this->settings->path . '/' . $path;
    }

    private function buildPath(string $uuid, string $extension): string
    {
        $clean = str_replace('-', '', $uuid);

        if (strlen($clean) < 4) {
            throw new RuntimeException("Invalid UUID: $uuid");
        }

        $first  = substr($clean, 0, 2);
        $second = substr($clean, 2, 2);

        return "$first/$second/$uuid.$extension";
    }

    private function cleanupEmptyDirs(string $dir): void
    {
        $root = $this->settings->path;
        while (str_starts_with($dir, $root) && $dir !== $root) {
            if (!is_dir($dir) || count(scandir($dir)) > 2) {
                return;
            }

            rmdir($dir);
            $dir = dirname($dir);
        }
    }
}