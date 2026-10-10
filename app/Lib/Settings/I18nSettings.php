<?php
declare(strict_types=1);

namespace App\Lib\Settings;

final readonly class I18nSettings 
{
    public function __construct(
        public string $default,
        public array $available,
        public string $path
    ){}
}