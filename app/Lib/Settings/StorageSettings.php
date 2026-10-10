<?php
declare(strict_types=1);

namespace App\Lib\Settings;

final readonly class StorageSettings 
{
    public function __construct(
        public array $allowed,
        public array $maxSizes,
    ){}
}