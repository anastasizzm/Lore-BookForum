<?php
declare(strict_types=1);

namespace App\Repositories\RuleProviders\Concrete;

use App\Models\Scripts\ScriptParam;
use App\Models\Access\Rules\OperationChecker;
use App\Models\Access\Rules\AccountAccessRules;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\ScriptBuilder;

use App\Lib\Data\Database;
use PDO;

final class AccountRuleProvider extends Repository implements RuleProvider 
{
    public function __construct(
        Database $db
    ){
        parent::__construct($db);
    }

    public function getRule(int $userId, int|string|object $entityId) : OperationChecker 
    {
        $entityId = (int)$entityId;
        if ($entityId == $userId)
            return new AccountAccessRules(manage: true, block: false);

        $builder = ScriptBuilder::withTable("roles_users", "ru");
        $builder->addSelect("ru.user_id")
            ->addSelect("COALESCE(bool_or(rp.sys_manage_accounts), false) as manage")
            ->addJoin("INNER JOIN roles_permissions rp ON rp.role_id = ru.role_id AND rp.publication_id IS NOT DISTINCT FROM ru.publication_id")
            ->addWhere("ru.is_valid")
            ->addWhere("ru.user_id = :userId", [":userId" => ScriptParam::asInt($userId)])
            ->addWhere("ru.publication_id IS NULL")
            ->setGroup("ru.user_id");

        $stmt = $this->execute($builder->build(), $builder->getParams());
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false)
            return AccountAccessRules::fromEmpty();
        else {
            $row['block'] = $row['manage']; // The user has access to block if he has access to system accounts manage
            return AccountAccessRules::fromRow($row);
        }
    }
}