<?php
declare(strict_types=1);

namespace App\Lib\Settings;

final readonly class ViewsSettings 
{
    private const string DATA_KEY = 'views';

    public function __construct(
        public string $pages,
        public string $layouts,
        public string $partials
    ){}

    public static function fromData(array $data) : self
    {
        $views = $data[self::DATA_KEY] ?? [];
        return new self(
            pages: rtrim($views['pages'] ?? '/pages', '/'),
            layouts: rtrim($views['layouts'] ?? '/layouts', '/'),
            partials: rtrim($views['partials'] ?? '/partials', '/')
        );
    }
}