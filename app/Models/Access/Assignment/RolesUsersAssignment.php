<?php
declare(strict_types=1);

namespace App\Models\Access\Assignment;
use App\Models\BasicModel;

use DateTimeImmutable;

readonly class RolesUsersAssignment extends BasicModel 
{
    public function __construct(
        int $id,
        string $title,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $deletedAt,
        public int $roleId,
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
            roleId: self::int($row, $prefix . 'role_id'),
            color: self::str($row, $prefix . 'color')
        );
    }

    public function toArray() : array 
    {
        $parent = parent::toArray();
        return $parent + [
            'roleId' => $this->roleId,
            'color' => $this->color
        ];
    }
}