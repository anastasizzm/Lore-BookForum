<?php
declare(strict_types=1);

namespace App\Lib\Settings;
use App\Models\Enums\FileCategory;

use RuntimeException;

final readonly class StorageSettings 
{
    private const string DATA_KEY = 'storage';

    public function __construct(
        public string $path,
        public string $internalUrlPrefix,
        public bool $useXAccel,
        private array $fileCategories,
    ){}

    public static function fromData(array $data) : self
    {
        $storage = $data[self::DATA_KEY] ?? [];
        return new self(
            path: $storage['path'] ?? throw new RuntimeException('No uploads path provided'),
            internalUrlPrefix: $storage['internal_url_prefix'] ?? '/internal-files',
            useXAccel: $storage['use_x_accel'] ?? false,
            fileCategories: self::normalizeCategories($storage['categories'] ?? [])
        );
    }

    public function fileMaxSize(FileCategory $category): int
    {
        return $this->fileConfig($category)['max_size'];
    }

    /** @return array<string, string> MIME → расширение */
    public function fileAllowedMimes(FileCategory $category): array
    {
        return $this->fileConfig($category)['mimes'];
    }

    public function fileIsPublic(FileCategory $category): bool
    {
        return $this->fileConfig($category)['public'];
    }

    // ---------- internals ----------

    /**
     * @return array{max_size: int, mimes: array<string, string>, public: bool}
     */
    private function fileConfig(FileCategory $category): array
    {
        $config = $this->fileCategories[$category->value] ?? null;

        if ($config === null) {
            throw new RuntimeException(
                "File category '{$category->value}' is not configured in settings"
            );
        }

        return $config;
    }

    /**
     * @param  array<string, mixed> $raw
     * @return array<string, array{max_size: int, mimes: array<string, string>, public: bool}>
     */
    private static function normalizeCategories(array $raw): array
    {
        $result = [];

        foreach ($raw as $name => $config) {
            if (!is_array($config)) {
                throw new RuntimeException("Invalid config for file category '$name'");
            }

            $maxSize = $config['max_size'] ?? null;
            $mimes   = $config['mimes'] ?? null;
            $public  = $config['public'] ?? false;

            if (!is_int($maxSize) || $maxSize <= 0) {
                throw new RuntimeException("Invalid 'max_size' for category '$name'");
            }

            if (!is_array($mimes) || $mimes === []) {
                throw new RuntimeException("Invalid 'mimes' for category '$name'");
            }

            $result[$name] = [
                'max_size' => $maxSize,
                'mimes'    => $mimes,
                'public'   => (bool) $public,
            ];
        }

        return $result;
    }
}