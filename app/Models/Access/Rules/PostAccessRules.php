<?php
declare(strict_types=1);

namespace App\Models\Access\Rules;
use App\Models\Access\Operations\AccessOperation;

use App\Models\Access\Operations\Posts\PostAccessOperation;
use App\Models\Access\Operations\Posts\CreatePostOperation;
use App\Models\Access\Operations\Posts\DeletePostOperation;
use App\Models\Access\Operations\Posts\UpdatePostOperation;

use App\Models\Dto;

class PostAccessRules extends Dto implements OperationChecker
{
    public function __construct(
        public bool $create,
        public bool $update,
        public bool $delete
    ){}

    public function hasAny() : bool 
    {
        return $this->create || $this->update || $this->delete;
    }

    public function allows(AccessOperation $operation) : bool
    {
        return match(true) {
            !($operation instanceof PostAccessOperation) => false,
            $operation instanceof CreatePostOperation => $this->create,
            $operation instanceof UpdatePostOperation => $this->update,
            $operation instanceof DeletePostOperation => $this->delete,
            default => false,
        };
    }

    public static function fromEmpty() : self 
    {
        return new self(
            false, false, false
        );
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            create: self::bool($row, $prefix . 'create'),
            update: self::bool($row, $prefix . 'update'),
            delete: self::bool($row, $prefix . 'delete'),
        );
    }
}