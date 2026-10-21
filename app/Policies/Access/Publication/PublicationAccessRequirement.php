<?php
declare(strict_types=1);

namespace App\Policies\Access\Publication;

use App\Policies\Access\AccessRequirement;
use App\Models\Enums\ServiceType;
use App\Models\Access\Operations\AccessOperation;

final class PublicationAccessRequirement extends AccessRequirement
{
    public function __construct(
        string $pathPattern,
        AccessOperation $operation,
    ){
        parent::__construct($pathPattern, $operation);
    }

    public function getTypeKey() : mixed { return ServiceType::Publication; }
    public function describe() : string { return 'errors.policies.access.publication'; }
}
