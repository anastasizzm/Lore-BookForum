<?php
declare(strict_types=1);

namespace App\Lib\Settings;

use RuntimeException;

final readonly class StorageSettings 
{
    private const string DATA_KEY = 'storage';

    public function __construct(
        public string $path,
        public array $allowed,
        public array $maxSizes,
        public string $internalUrlPrefix,
        public bool $useXAccel
    ){}

    public static function fromData(array $data) : self
    {
        $storage = $data[self::DATA_KEY] ?? [];
        return new self(
            path: $storage['path'] ?? throw new RuntimeException('No uploads path provided'),
            allowed: $storage['allowed'] ?? [],
            maxSizes: $storage['max_sizes'] ?? [],
            internalUrlPrefix: $storage['internal_url_prefix'] ?? '/internal-files',
            useXAccel: $storage['use_x_accel'] ?? false
        );
    }
}