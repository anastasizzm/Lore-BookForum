<?php
declare(strict_types=1);

namespace App\Models\Additional;

use App\Models\BasicModel;
use DateTimeImmutable;

final readonly class UserRole extends BasicModel
{
    public function __construct(
        int $id, 
        string $title,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $deletedAt,
        public string $color
    ){
        parent::__construct($id, $title, $createdAt, $deletedAt);
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            createdAt: $parent->createdAt,
            deletedAt: $parent->deletedAt,
            color: self::str($row, $prefix . 'color')
        );
    }

    public function toArray() : array 
    {
        $parent = parent::toArray();
        return $parent + [
            'color' => $this->color
        ];
    }
}