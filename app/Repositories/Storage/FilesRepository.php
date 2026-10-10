<?php
declare(strict_types=1);

namespace App\Storage\Repositories;

use App\Repositories\Repository;

use App\Files\FileCategory;
use App\Lib\Database;
use App\Models\Uuid;
use App\Models\File;

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
        string $category,
        string $originalName,
        string $contentType,
        int $size,
        int $uploadedBy,
    ): void {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO files (id, path, category, original_name, content_type, size_bytes, uploaded_by)
             VALUES (:id, :path, :category, :name, :contentType, :size, :uploadedBy)'
        );

        $stmt->execute([
            ':id' => $id->toString(),
            ':path' => $path,
            ':category' => $category,
            ':name' => $originalName,
            ':contentType' => $contentType,
            ':size' => $size,
            ':uploadedBy' => $uploadedBy,
        ]);
    }

    public function findById(Uuid $id): ?File
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT id, path, category, original_name, content_type, size_bytes, uploaded_by, created_at
             FROM files WHERE id = :id'
        );
        $stmt->execute([':id' => $id->toString()]);
        $row = $stmt->fetch();

        return $row === false ? null : File::fromRow($row);
    }

    public function delete(Uuid $id): void
    {
        $pdo->prepare('DELETE FROM files WHERE id = :id')
            ->execute([':id' => $id->toString()]);
    }
}