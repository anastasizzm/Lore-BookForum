<?php
declare(strict_types=1);

namespace App\Services\Storage;

use App\Exceptions\ValidationException;
use App\Files\FileStorage;
use App\Lib\UnitOfWork;
use App\Models\File;
use App\Models\Uuid;
use App\Repositories\FilesRepository;
use PDO;
use RuntimeException;

final class FileService
{
    private const array ALLOWED = [
        'avatar' => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ],
        'cover' => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ],
        'book' => [
            'application/pdf'      => 'pdf',
            'application/epub+zip' => 'epub',
            'application/x-mobipocket-ebook' => 'mobi',
            'application/x-fictionbook+xml'  => 'fb2',
        ],
    ];

    private const array MAX_SIZE = [
        'avatar' => 2 * 1024 * 1024,        // 2 МБ
        'cover'  => 5 * 1024 * 1024,        // 5 МБ
        'book'   => 100 * 1024 * 1024,      // 100 МБ
    ];

    public function __construct(
        private readonly FileStorage     $storage,
        private readonly FilesRepository $files,
        private readonly UnitOfWork      $uow,
    ) {}

    /**
     * @param  array<string, mixed> $upload  из $_FILES / $ctx->request->files
     * @throws ValidationException
     */
    public function store(
        array   $upload,
        string  $category,
        ?int    $ownerId,
    ): File {
        $this->validateUpload($upload, $category);

        $tmpPath = $upload['tmp_name'];
        $mime    = $this->detectMime($tmpPath, $category);
        $ext     = self::ALLOWED[$category][$mime];
        $uuid    = Uuid::generate();

        // 1. Кладём файл в storage
        $stored = $this->storage->store(
            localPath: $tmpPath,
            category:  $category,
            uuid:      $uuid->toString(),
            extension: $ext,
            mimeType:  $mime,
        );

        // 2. Записываем в БД
        try {
            return $this->uow->transactional(function (PDO $pdo) use (
                $uuid, $stored, $category, $upload, $ownerId
            ): File {
                $this->files->create(
                    pdo:          $pdo,
                    id:           $uuid,
                    path:         $stored->path,
                    category:     $category,
                    originalName: (string) $upload['name'],
                    mimeType:     $stored->mimeType,
                    size:         $stored->size,
                    ownerId:      $ownerId,
                );

                return new File(
                    id:           $uuid,
                    path:         $stored->path,
                    category:     $category,
                    originalName: (string) $upload['name'],
                    mimeType:     $stored->mimeType,
                    size:         $stored->size,
                    ownerId:      $ownerId,
                    createdAt:    new \DateTimeImmutable(),
                );
            });
        } catch (\Throwable $e) {
            // БД упала — удаляем файл, чтобы не копить мусор
            $this->storage->delete($stored->path);
            throw $e;
        }
    }

    public function delete(Uuid $id): void
    {
        $file = $this->files->findById($id);

        if ($file === null) {
            return;
        }

        $this->uow->transactional(function (PDO $pdo) use ($id): void {
            $this->files->delete($pdo, $id);
        });

        // Файл удаляем ПОСЛЕ коммита. Ошибка не критична.
        try {
            $this->storage->delete($file->path);
        } catch (\Throwable $e) {
            error_log("[files] failed to delete {$file->path}: {$e->getMessage()}");
        }
    }

    /** @param array<string, mixed> $upload */
    private function validateUpload(array $upload, string $category): void
    {
        if (!isset(self::ALLOWED[$category])) {
            throw new RuntimeException("Unknown file category: $category");
        }

        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException([
                'file' => ["Upload failed (error code {$upload['error']})"],
            ]);
        }

        $size = (int) ($upload['size'] ?? 0);

        if ($size <= 0) {
            throw new ValidationException(['file' => ['File is empty']]);
        }

        if ($size > self::MAX_SIZE[$category]) {
            $maxMb = self::MAX_SIZE[$category] / 1024 / 1024;
            throw new ValidationException([
                'file' => ["File is too large (max {$maxMb} MB)"],
            ]);
        }

        if (!is_uploaded_file((string) $upload['tmp_name'])) {
            throw new ValidationException(['file' => ['Invalid upload']]);
        }
    }

    private function detectMime(string $path, string $category): string
    {
        $mime = mime_content_type($path);

        if ($mime === false || !isset(self::ALLOWED[$category][$mime])) {
            throw new ValidationException([
                'file' => ["Unsupported file format: " . ($mime ?: 'unknown')],
            ]);
        }

        return $mime;
    }
}