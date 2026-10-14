<?php
declare(strict_types=1);

namespace App\Models\Files;

final readonly class UploadedFile 
{
    public function __construct(
        public string $tmpPath,
        public string $originalName,
        public ?string $mime,
        public int $size,
        public int $error,
    ) {}

    public static function fromInput(array $file): self
    {
        foreach (['tmp_name', 'name', 'type', 'size', 'error'] as $key) {
            if (!array_key_exists($key, $file)) {
                throw new InvalidArgumentException(
                    "Malformed file array: missing key '$key'"
                );
            }
        }

        $filePath = (string)$file['tmp_name'];
        return new self(
            tmpPath: $filePath,
            originalName: (string)$file['name'],
            mime: self::detectMime($filePath),
            size: (int)$file['size'],
            error: (int)$file['error'],
        );
    }

    private static function detectMime(string $path) : ?string {
        return mime_content_type($path) ?? NULL;
    }

    public function isOk(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    public function isUploaded(): bool
    {
        return is_uploaded_file($this->tmpPath);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function humanSize(): string
    {
        $bytes = $this->size;

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}