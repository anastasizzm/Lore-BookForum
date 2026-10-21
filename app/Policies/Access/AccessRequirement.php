<?php
declare(strict_types=1);

namespace App\Policies\Access;

use App\Lib\Auth\AuthorizationRequirement;
use App\Models\Access\Operations\AccessOperation;

abstract class AccessRequirement implements AuthorizationRequirement
{
    public function __construct(
        public readonly string $pathPattern,
        public readonly AccessOperation $operation,
    ){}

    public abstract function getTypeKey() : mixed;
    public abstract function describe() : string;
}
