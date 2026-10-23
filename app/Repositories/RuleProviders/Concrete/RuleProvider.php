<?php
declare(strict_types=1);

namespace App\Repositories\RuleProviders\Concrete;
use App\Models\Access\Rules\OperationChecker;

interface RuleProvider 
{
    public function getRule(int $userId, int|string $entityId) : OperationChecker;

    /** @param array<int|string> $entities 
     * @return array<int|string, OperationChecker>
    */
    public function getRulesMany(int $userId, array $entities) : array;
}