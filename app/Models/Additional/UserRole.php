<?php
declare(strict_types=1);

namespace App\Models\Additional;

use App\Models\BasicModel;

final readonly class Language extends BasicModel
{
    public function __construct(
        public string $color
    ){}

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            createdAt: $parent->createdAt,
            color: self::str($row, $prefix . 'color')
        );
    }
}