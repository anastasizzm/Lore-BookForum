<?php
declare(strict_types=1);

namespace App\Repositories\RuleProviders\Common;
use App\Models\Access\Rules\OperationChecker;

interface CommonRuleProvider 
{
    public function getRule(int $userId) : OperationChecker;
}