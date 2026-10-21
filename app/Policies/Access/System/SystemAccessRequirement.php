<?php
declare(strict_types=1);

namespace App\Policies\Access\System;

use App\Policies\Access\AccessRequirement;
use App\Models\Enums\ServiceType;
use App\Models\Access\Operations\AccessOperation;

final class SystemAccessRequirement extends AccessRequirement
{
    public function __construct(
        string $pathPattern,
        AccessOperation $operation,
    ){
        parent::__construct($pathPattern, $operation);
    }

    public function getTypeKey() : string { return ServiceType::System; }
    public function describe() : string { return 'errors.policies.access.system'; }
}
