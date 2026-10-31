<?php
namespace App\Models;

use DateTimeImmutable;

use App\Models\Dto;

readonly class BasicModel extends Dto
{
    public function __construct(
        public int $id,
        public string $title,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $deletedAt
    ){}

    public function id() : mixed { return $this->id; }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            id: self::int($row, $prefix . 'id'),
            title: self::str($row, $prefix . 'title'),
            createdAt: self::dt($row, $prefix . 'created_at'),
            deletedAt: self::dtN($row, $prefix .'deleted_at')
        );
    }

    public function toArray() : array 
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'createdAt' => $this->createdAt,
            'deletedAt' => $this->deletedAt
        ];
    }

    public function isActive() : bool { return $this->deletedAt === NULL; }
}