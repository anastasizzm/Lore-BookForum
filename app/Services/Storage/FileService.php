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
        array $upload,
        string $category,
        int $uploadedBy
    ): File {
        $this->validateUpload($upload, $category);

        $tmpPath = $upload['tmp_name'];
        $mime    = $this->detectMime($tmpPath, $category);
        $ext     = $this->settings->allowed[$category][$mime];
        $uuid    = Uuid::generate();

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
                    originalName: (string) $upload['name'],
                    mimeType: $stored->mimeType,
                    size: $stored->size,
                    uploadedBy: $uploadedBy,
                );

                return new File(
                    id: $uuid,
                    path: $stored->path,
                    category: $category,
                    originalName: (string) $upload['name'],
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

    /** @param array<string, mixed> $upload */
    private function validateUpload(array $upload, string $category): void
    {
        if (!isset($this->settings->allowed[$category])) {
            throw new RuntimeException("Unknown file category: $category");
        }

        $errors = [];
        $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $errors['file'][] = $this->translator->t($this->describeUploadError($error));
        }

        if (!is_uploaded_file((string) $upload['tmp_name'])) {
            $errors['file'][] = $this->translator->t('errors.files.invalid_upload');
        }

        $size = (int) ($upload['size'] ?? 0);
        if ($size <= 0) {
            $errors['file'][] = $this->translator->t('errors.files.file_empty');
        }

        $categoryMaxSize = this->settings->maxSizes[$category];
        if ($size > $categoryMaxSize) {
            $maxMb = $categoryMaxSize / 1024 / 1024;
            $errors['file'][] = $this->translator->t('errors.files.file_large', [":max" => $maxMb]);
        }

        if ($errors != [])
            throw new ValidationException($errors);
    }

    private function describeUploadError(int $error): string
    {   
        return match ($error) {
            UPLOAD_ERR_INI_SIZE   => 'errors.files.ini_size',
            UPLOAD_ERR_FORM_SIZE  => 'errors.files.form_size',
            UPLOAD_ERR_PARTIAL    => 'errors.files.partial',
            UPLOAD_ERR_NO_FILE    => 'errors.files.no_file',
            UPLOAD_ERR_NO_TMP_DIR => 'errors.files.no_tmp_dir',
            UPLOAD_ERR_CANT_WRITE => 'errors.files.cant_write',
            UPLOAD_ERR_EXTENSION  => 'errors.files.extension',
            default               => 'errors.files.upload_fail',
        };
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