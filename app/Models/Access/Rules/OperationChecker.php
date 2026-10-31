<?php
declare(strict_types=1);

namespace App\Models\Access\Rules;

use App\Models\Access\Operations\AccessOperation;

interface OperationChecker 
{
    public function hasAny() : bool;
    public function allows(AccessOperation $operation) : bool;
    
    public function toArray(): array;
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self;
}