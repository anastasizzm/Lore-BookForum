<?php
declare(strict_types=1);

namespace App\Lib\Settings;

final readonly class ViewsSettings 
{
    public function __construct(
        public string $pages,
        public string $layouts,
        public string $partials
    ){}
}