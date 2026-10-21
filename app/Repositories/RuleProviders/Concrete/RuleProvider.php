<?php
declare(strict_types=1);

namespace App\Repositories\RuleProviders\Concrete;
use App\Models\Access\Rules\OperationChecker;

interface RuleProvider 
{
    public function getRule(int $userId, int|string|object $entityId) : OperationChecker;
}