<?php
declare(strict_types=1);

namespace App\Models\Additional;

use App\Models\Dto;

final readonly class Language extends Dto
{
    public function __construct(
        public string $code,
        public bool $isActive
    ){}

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            code: self::str($row, $prefix . 'code'),
            isActive: self::bool($row, $prefix . 'is_active')
        );
    }
}