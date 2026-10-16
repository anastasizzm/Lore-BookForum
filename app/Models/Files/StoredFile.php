<?php
declare(strict_types=1);

namespace App\Models\Files;

final readonly class StoredFile
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public int $size,
    ) {}
}