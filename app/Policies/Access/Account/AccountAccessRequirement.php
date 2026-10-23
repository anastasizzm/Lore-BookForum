<?php
declare(strict_types=1);

namespace App\Policies\Access\Account;

use App\Policies\Access\AccessRequirement;
use App\Models\Access\Operations\AccessOperation;
use App\Models\Enums\ServiceType;

final class AccountAccessRequirement extends AccessRequirement
{
    public function __construct(
        string $pathPattern,
        AccessOperation $operation,
    ){
        parent::__construct($pathPattern, $operation);
    }

    public function getTypeKey() : mixed { return ServiceType::Account; }
    public function describe() : string { return 'errors.policies.access.account'; }
}
