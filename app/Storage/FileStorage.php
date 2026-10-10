<?php
declare(strict_types=1);

namespace App\Storage;

interface FileStorage
{
    /**
     * Кладёт файл в storage. Возвращает относительный путь.
     *
     * @param string $localPath  путь к временному файлу (tmp upload)
     * @param string $category   'avatar' | 'cover' | 'book'
     */
    public function store(
        string $localPath,
        string $category,
        string $uuid,
        string $extension,
        string $mimeType,
    ): StoredFile;

    public function delete(string $path): void;

    public function exists(string $path): bool;

    /** Абсолютный путь для чтения (PHP-стриминг, X-Accel). */
    public function absolutePath(string $path): string;
}