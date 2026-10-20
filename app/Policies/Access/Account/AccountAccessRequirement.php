<?php
declare(strict_types=1);

namespace App\Policies\Access\Account;

use App\Policies\Access\AccessRequirement;
use App\Models\Access\Operations\AccessOperation;

final class AccountAccessRequirement extends AccessRequirement
{
    public const CONTAINER_KEY = 'ACCOUNT_ACCESS';

    public function __construct(
        public readonly string $pathPattern,
        public readonly AccessOperation $operation,
    ){
        parent::__construct($pathPattern, $operation);
    }

    public function getTypeKey() : string { return self::CONTAINER_KEY; }
    public function describe() : string { return 'errors.policies.access.account'; }
}
