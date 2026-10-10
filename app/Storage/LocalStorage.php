<?php
declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

final class LocalFileStorage implements FileStorage
{
    public function __construct(
        private readonly string $root,
        private readonly int $permissions = 0755,
    ) {
        $this->root = rtrim($root, '/\\');

        if (!is_dir($this->root)) {
            if (!mkdir($this->root, $this->permissions, true) && !is_dir($this->root)) {
                throw new RuntimeException("Cannot create storage root: {$this->root}");
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
        $absolute = $this->root . '/' . $relative;

        $dir = dirname($absolute);

        if (!is_dir($dir) && !mkdir($dir, $this->permissions, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create directory: $dir");
        }

        // move_uploaded_file только для tmp upload; для локального файла — rename
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
        $absolute = $this->root . '/' . $path;

        if (is_file($absolute)) {
            unlink($absolute);

            // Опционально: удалить пустые префиксные папки
            $this->cleanupEmptyDirs(dirname($absolute));
        }
    }

    public function exists(string $path): bool
    {
        return is_file($this->root . '/' . $path);
    }

    public function absolutePath(string $path): string
    {
        return $this->root . '/' . $path;
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
        // Не поднимаемся выше root
        while (str_starts_with($dir, $this->root) && $dir !== $this->root) {
            if (!is_dir($dir) || count(scandir($dir)) > 2) {
                return;   // не пустая
            }

            rmdir($dir);
            $dir = dirname($dir);
        }
    }
}