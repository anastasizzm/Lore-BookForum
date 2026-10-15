<?php
declare(strict_types=1);

namespace App\Models\Files;

use DateTimeImmutable;
use App\Models\Uuid;
use App\Models\Enums\FileCategory;
use App\Models\Enums\FileStatus;
use App\Models\Dto;

final readonly class File extends Dto
{
    public function __construct(
        public Uuid $id,
        public string $path,
        public FileCategory $category,
        public FileStatus $status,
        public string $originalName,
        public string $format,
        public string $mimeType,
        public int $size,
        public int $uploadedBy,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            id: self::uuid($row, $prefix . 'id'),
            path: self::str($row, $prefix . 'path'),
            category: self::enum($row, $prefix . 'category', FileCategory::class),
            status: self::enum($row, $prefix . 'status', FileStatus::class),
            originalName: self::str($row, $prefix . 'original_name'),
            format: self::str($row, $prefix . 'format'),
            mimeType: self::str($row, $prefix . 'mime_type'),
            size: self::int($row, $prefix . 'size_bytes'),
            uploadedBy: self::int($row, $prefix . 'uploaded_by'),
            createdAt: self::dt($row, $prefix . 'created_at')
        );
    }
}