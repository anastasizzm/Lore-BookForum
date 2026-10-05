<?php
namespace App\Models;

use DateTimeImmutable;

use App\Models\Dto;

readonly class BasicModel extends Dto
{
    public function __construct(
        public int $id,
        public string $title,
        public DateTimeImmutable $createdAt
    ){}

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            id: self::int($row, $prefix . 'id'),
            title: self::str($row, $prefix . 'title'),
            createdAt: self::dt($row, $prefix . 'created_at')
        );
    }

    public function toArray() : array 
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'createdAt' => $this->createdAt
        ];
    }
}