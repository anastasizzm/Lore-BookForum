<?php
declare(strict_types=1);

namespace App\Policies\Access\Post;

use App\Policies\Access\AccessRequirement;
use App\Models\Enums\ServiceType;
use App\Models\Access\Operations\AccessOperation;

final class PostAccessRequirement extends AccessRequirement
{
    public function __construct(
        string $pathPattern,
        AccessOperation $operation,
    ){
        parent::__construct($pathPattern, $operation);
    }

    public function getTypeKey() : mixed { return ServiceType::Post; }
    public function describe() : string { return 'errors.policies.access.post'; }
}
