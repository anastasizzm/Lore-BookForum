<?php
declare(strict_types=1);

namespace App\Services\Storage;

use App\Repositories\Storage\FilesRepository;
use App\Exceptions\ValidationException;
use App\Storage\FileStorage;
use App\Lib\Settings\StorageSettings;
use App\Lib\I18n\Translator;
use App\Services\Configuration\UnitOfWork;
use App\Models\Files\File;
use App\Models\Files\UploadedFile;
use App\Models\Uuid;

use PDO;
use RuntimeException;

final class FileService
{
    public function __construct(
        private readonly StorageSettings $settings,
        private readonly FileStorage $storage,
        private readonly FilesRepository $files,
        private readonly UnitOfWork $uow,
        private readonly Translator $translator
    ) {}

    public function store(
        UploadedFile $upload,
        string $category,
        int $uploadedBy
    ): File {
        $tmpPath = $upload->tmpPath;
        $mime = $upload->mime ?? $this->detectMime($tmpPath, $category);
        $ext = $this->settings->allowed[$category][$mime];
        $uuid = Uuid::generate();

        $stored = $this->storage->store(
            localPath: $tmpPath,
            category:  $category,
            uuid:      $uuid->toString(),
            extension: $ext,
            mimeType:  $mime,
        );

        try {
            return $this->uow->transactional(function (PDO $pdo) use (
                $uuid, $stored, $category, $upload, $uploadedBy
            ): File {
                $this->files->create(
                    id: $uuid,
                    path: $stored->path,
                    category: $category,
                    originalName: $upload->originalName,
                    format: $ext,
                    mimeType: $stored->mimeType,
                    size: $stored->size,
                    uploadedBy: $uploadedBy,
                );

                return new File(
                    id: $uuid,
                    path: $stored->path,
                    category: $category,
                    originalName: $upload->originalName,
                    format: $ext,
                    mimeType: $stored->mimeType,
                    size: $stored->size,
                    uploadedBy: $uploadedBy,
                    createdAt: new \DateTimeImmutable(),
                );
            });
        } catch (\Throwable $e) {
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
            $this->files->delete($id);
        });

        try {
            $this->storage->delete($file->path);
        } catch (\Throwable $e) {
            error_log("[files] failed to delete {$file->path}: {$e->getMessage()}");
        }
    }

    private function detectMime(string $path, string $category): string
    {
        $mime = mime_content_type($path);

        if ($mime === false || !isset($this->settings->allowed[$category][$mime])) {
            throw new ValidationException([
                'file' => [$this->translator->t('errors.files.file_format', [":format" => $mime ?: 'unknown'])]
            ]);
        }

        return $mime;
    }
}