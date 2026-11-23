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

    /** @param array<Uuid> $ids @return array<string, File> keyed by file id */
    public function findByIds(array $ids): array
    {
        if ($ids === []) return [];

        $fileIdsArray = '{' . implode(',', array_map(
            static fn(Uuid $id) => $id->toString(),
            $ids
        )) . '}';

        $stmt = $this->pdo()->prepare(
            'SELECT id, path, category, status, original_name, format, mime_type, size_bytes, uploaded_by, created_at
             FROM files WHERE id = ANY(:ids::uuid[])'
        );
        $stmt->execute([':ids' => $fileIdsArray]);

        $byId = [];
        foreach ($stmt->fetchAll() as $row) {
            $file = File::fromRow($row);
            $byId[$file->id->toString()] = $file;
        }

        return $byId;
    }

    public function isOwnedBy(Uuid $id, int $userId): bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT 1 FROM files WHERE id = :id AND uploaded_by = :uploadedBy LIMIT 1'
        );
        $stmt->execute([':id' => $id->toString(), ':uploadedBy' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * A private file is readable when it is attached to a book the user may
     * read: public book, owned book, or a publication-based role with pub_read.
     */
    public function isReadablePublicationFile(Uuid $id, int $userId): bool
    {
        $stmt = $this->pdo()->prepare(
            "SELECT 1
             FROM books_content bc
             INNER JOIN publications p ON p.id = bc.book_id
             WHERE bc.content_id = :fileId
               AND p.is_active
               AND (
                   p.status = 'public'
                   OR p.owner_id = :ownerId
                   OR EXISTS (
                       SELECT 1
                       FROM roles_users ru
                       INNER JOIN roles_permissions rp
                               ON rp.role_id = ru.role_id
                               AND rp.publication_id IS NOT DISTINCT FROM ru.publication_id
                       WHERE ru.user_id = :roleUserId
                         AND ru.is_valid
                         AND ru.publication_id = p.id
                         AND rp.pub_read
                   )
               )
             LIMIT 1"
        );
        $stmt->execute([
            ':fileId' => $id->toString(),
            ':ownerId' => $userId,
            ':roleUserId' => $userId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function findByHash(string $hash, int $uploadedBy, FileCategory $category): ?File
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, path, category, status, original_name, format, mime_type, size_bytes, uploaded_by, created_at
             FROM files
             WHERE hash = :hash
               AND uploaded_by = :uploadedBy
               AND category = :category
             LIMIT 1'
        );
        $stmt->execute([
            ':hash' => $hash,
            ':uploadedBy' => $uploadedBy,
            ':category' => $category->value,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : File::fromRow($row);
    }

    public function delete(Uuid $id): void
    {
        $this->pdo()->prepare('DELETE FROM files WHERE id = :id')
            ->execute([':id' => $id->toString()]);
    }

    public function activate(Uuid $id) : void 
    {
        $this->pdo()->prepare("UPDATE files SET status = 'active' WHERE id = :fileId::uuid")
            ->execute([':fileId' => $id->toString()]);
    }

    /** @param array<Uuid> $ids */
    public function activateMany(array $ids) : void 
    {
        $fileIdsArray = '{' . implode(',', $ids) . '}';
        $stmt = $this->pdo()->prepare("UPDATE files SET status = 'active' WHERE id = ANY(:fileIds::uuid[])");
        $stmt->execute([':fileIds' => $fileIdsArray]);
    }
}