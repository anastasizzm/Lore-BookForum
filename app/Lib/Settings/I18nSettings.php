<?php
declare(strict_types=1);

namespace App\Lib\Settings;

final readonly class I18nSettings 
{
    private const string DATA_KEY = 'i18n';

    public function __construct(
        public string $default,
        public array $available,
        public string $path
    ){}

    public static function fromData(array $data) : self
    {
        $i18n = $data[self::DATA_KEY] ?? [];
        return new self(
            default: $i18n['default'] ?? 'en',
            available: $i18n['available'] ?? ['en'],
            path: rtrim($i18n['path'] ?? __DIR__ . '/resources/lang', '/\\')
        );
    }
}