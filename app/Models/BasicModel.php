<?php
namespace App\Models;

use DateTimeImmutable;

use App\Models\Dto;

readonly class BasicModel extends Dto
{
    public function __construct(
        public int $id,
        public string $title,
    ){}

    public static function fromRow(string $row, string $prefix = '') : self 
    {
        return new self(
            id: self::int($row, $prefix, 'id'),
            title: self::str($row, $pregix, 'title')
        );
    }
}