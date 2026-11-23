<?php
declare(strict_types=1);

namespace App\Services\Storage;

use App\Repositories\Storage\FilesRepository;
use App\Exceptions\ValidationException;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\Translators\FileExceptionTranslator;
use App\Storage\FileStorage;
use App\Lib\Settings\StorageSettings;
use App\Lib\I18n\Translator;
use App\Services\Configuration\UnitOfWork;
use App\Validators\FilesValidator;
use App\Models\Files\File;
use App\Models\Enums\FileCategory;
use App\Models\Enums\FileStatus;
use App\Models\Files\UploadedFile;
use App\Models\Errors\ErrorBag;
use App\Models\Uuid;

use PDO;
use RuntimeException;

final class FileService
{
    public function __construct(
        private readonly StorageSettings $settings,
        private readonly FileStorage $storage,
        private readonly FilesRepository $files,
        private readonly FilesValidator $validator,
        private readonly UnitOfWork $uow,
        private readonly Translator $translator,
        private readonly FileExceptionTranslator $exceptionTranslator
    ) {}

    public function storePending(UploadedFile $upload, FileCategory $category, int $creatorId): File 
    {
        return $this->store($upload, $category, $creatorId, FileStatus::Pending);
    }

    public function store(
        UploadedFile $upload,
        FileCategory $category,
        int $creatorId,
        FileStatus $status = FileStatus::Active
    ): File {
        $bag = new ErrorBag();
        $this->validator->validateOneFile($upload, $category, 'file', $bag);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());

        $tmpPath = $upload->tmpPath;
        
        $hash = hash_file('sha256', $tmpPath);
        $existingFile = $this->files->findByHash($hash, $creatorId, $category);
        
        if ($existingFile !== null) {
            return $existingFile; 
        }

        $mime = $upload->mime ?? $this->detectMime($tmpPath, $category);
        $ext = $this->settings->fileAllowedMimes($category)[$mime];
        $uuid = Uuid::generate();

        $stored = $this->storage->store(
            localPath: $tmpPath,
            uuid:      $uuid->toString(),
            extension: $ext,
            mimeType:  $mime,
        );

        try {
            // 3. Save to database
            return $this->uow->transactional(function (PDO $pdo) use (
                $uuid, $stored, $hash, $category, $upload, $creatorId, $status, $ext
            ): File {
                $this->files->create(
                    id: $uuid,
                    path: $stored->path,
                    hash: $hash,
                    category: $category,
                    status: $status,
                    originalName: $upload->originalName,
                    format: $ext,
                    mimeType: $stored->mimeType,
                    size: $stored->size,
                    uploadedBy: $creatorId,
                );

                return new File(
                    id: $uuid,
                    path: $stored->path,
                    category: $category,
                    originalName: $upload->originalName,
                    status: $status,
                    format: $ext,
                    mimeType: $stored->mimeType,
                    size: $stored->size,
                    uploadedBy: $creatorId,
                    createdAt: new \DateTimeImmutable(),
                );
            });
        } catch (\PDOException $e) {
            $this->storage->delete($stored->path);

            if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'files_hash_')) {
                $existingFile = $this->files->findByHash($hash, $creatorId, $category);
                if ($existingFile !== null) {
                    return $existingFile;
                }
            }

            throw $this->exceptionTranslator->translate($e);
        } catch (\Throwable $e) {
            $this->storage->delete($stored->path);
            throw $e;
        }
    }

    public function activate(Uuid $id) : void
    {
        $this->files->activate($id);
    }

    /** @param array<Uuid> $ids */
    public function activateMany(array $ids) : void
    {
        $this->files->activateMany($ids);
    }

    public function retrieve(Uuid $fileId): ?File 
    {
        return $this->files->findById($fileId);
    }

    public function isOwnedBy(Uuid $fileId, int $userId): bool
    {
        return $this->files->isOwnedBy($fileId, $userId);
    }

    public function isReadablePublicationFile(Uuid $fileId, int $userId): bool
    {
        return $this->files->isReadablePublicationFile($fileId, $userId);
    }

    /**
     * Authorize a batch of file ids in a single query.
     *
     * A file is allowed when its category is public or when it was uploaded
     * by the current user. Missing ids are treated as not found. When a
     * required category is given, every file must have exactly that category.
     *
     * @param array<Uuid> $fileIds
     */
    public function authorizeBatch(array $fileIds, int $userId, ?FileCategory $requiredCategory = null): void
    {
        if ($fileIds === []) return;

        $files = $this->files->findByIds($fileIds);

        foreach ($fileIds as $fileId) {
            $file = $files[$fileId->toString()] ?? null;

            if ($file === null) {
                throw new NotFoundException($this->translator->t('errors.common.not_found'));
            }

            if ($requiredCategory !== null && $file->category !== $requiredCategory) {
                throw new ForbiddenException($this->translator->t('errors.common.forbidden'));
            }

            if (!$this->settings->fileIsPublic($file->category) && $file->uploadedBy !== $userId) {
                throw new ForbiddenException($this->translator->t('errors.common.forbidden'));
            }
        }
    }

    public function delete(Uuid $id): void
    {
        $file = $this->files->findById($id);
        if ($file === null) return;

        try {
            $this->uow->transactional(function () use ($id): void {
                $this->files->delete($id);
            });
        } catch (\PDOException $e) {
            if ($e->getCode() === '23001' || $e->getCode() === '23503') {
                throw new ConflictException($this->translator->t('errors.common.in_use'));
            }
            
            throw $this->exceptionTranslator->translate($e);
        }

        try {
            $this->storage->delete($file->path);
        } catch (\Throwable $e) {
            error_log("[files] failed to delete physical file {$file->path}: {$e->getMessage()}");
        }
    }

    private function detectMime(string $path, FileCategory $category): string
    {
        $mime = mime_content_type($path);
        $allowed = $this->settings->fileAllowedMimes($category);

        if ($mime === false || !isset($allowed[$mime])) {
            throw new ValidationException([
                'file' => [$this->translator->t('errors.files.file_format', [":format" => $mime ?: 'unknown'])]
            ]);
        }

        return $mime;
    }
}