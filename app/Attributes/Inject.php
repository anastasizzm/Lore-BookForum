<?php
declare(strict_types=1);

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Inject
{
    public function __construct(
        public readonly ?string $key = null,
        public readonly ?string $id  = null,
    ) {}
}