<?php
declare(strict_types=1);

namespace App\Storage;

use App\Models\Files\StoredFile;
use App\Models\Enums\FileCategory;

interface FileStorage
{
    /**
     * Кладёт файл в storage. Возвращает относительный путь.
     *
     * @param string $localPath  путь к временному файлу (tmp upload)
     */
    public function store(
        string $localPath,
        string $uuid,
        string $extension,
        string $mimeType,
    ): StoredFile;

    public function delete(string $path): void;

    public function exists(string $path): bool;

    public function absolutePath(string $path): string;
}