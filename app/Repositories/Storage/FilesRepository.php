<?php
declare(strict_types=1);

namespace App\Repositories\Storage;

use App\Repositories\Repository;

use App\Models\Enums\FileCategory;
use App\Models\Enums\FileStatus;
use App\Lib\Data\Database;
use App\Models\Uuid;
use App\Models\Files\File;

use DateTimeImmutable;
use PDO;

final class FilesRepository extends Repository
{
    public function __construct(
        Database $db
    ) {
        parent::__construct($db);
    }

    public function create(
        Uuid $id,
        string $path,
        string $hash,
        FileCategory $category,
        FileStatus $status,
        string $originalName,
        string $format,
        string $mimeType,
        int $size,
        int $uploadedBy,
    ): void {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO files (id, path, hash, category, status, original_name, format, mime_type, size_bytes, uploaded_by)
             VALUES (:id, :path, :hash, :category, :status, :name, :format, :mimeType, :size, :uploadedBy)'
        );

        $stmt->execute([
            ':id' => $id->toString(),
            ':path' => $path,
            ':hash' => $hash,
            ':status' => $status->value,
            ':category' => $category->value,
            ':name' => $originalName,
            ':format' => $format,
            ':mimeType' => $mimeType,
            ':size' => $size,
            ':uploadedBy' => $uploadedBy,
        ]);
    }

    public function findById(Uuid $id): ?File
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, path, category, status, original_name, format, mime_type, size_bytes, uploaded_by, created_at
             FROM files WHERE id = :id'
        );
        $stmt->execute([':id' => $id->toString()]);
        $row = $stmt->fetch();

        return $row === false ? null : File::fromRow($row);
    }

    public function findByHash(string $hash): ?File
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, path, category, status, original_name, format, mime_type, size_bytes, uploaded_by, created_at
             FROM files WHERE hash = :hash LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        $row = $stmt->fetch();

        return $row === false ? null : File::fromRow($row);
    }

    public function delete(Uuid $id): void
    {
        $this->pdo()->prepare('DELETE FROM files WHERE id = :id')
            ->execute([':id' => $id->toString()]);
    }
}