<?php
declare(strict_types=1);

namespace App\Services\Access;
use App\Repositories\RuleProviders\Concrete\RuleProvider;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Models\Access\Operations\AccessOperation;

final class AccessService 
{
    public function __construct(
        private readonly ?RuleProvider $ruleProvider = NULL,
        private readonly ?CommonRuleProvider $commonRuleProvider = NULL
    ){}

    public function hasAccess(int $userId, int|string|null $entityId, AccessOperation $operation) : bool 
    {
        $rules = NULL;

        if ($entityId === NULL){
            if ($this->commonRuleProvider !== NULL) $this->commonRuleProvider->getRule($userId);
        }
        else {
            if ($this->ruleProvider !== NULL) $this->ruleProvider->getRule($userId, $entityId);
        }

        return $rules?->allows($operation) ?? false;
    }
}