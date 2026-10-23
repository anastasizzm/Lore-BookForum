<?php
declare(strict_types=1);

namespace App\Repositories\RuleProviders\Common;

use App\Models\Scripts\ScriptParam;
use App\Models\Access\Rules\OperationChecker;
use App\Models\Access\Rules\SystemAccessRules;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\ScriptBuilder;

use App\Lib\Data\Database;
use PDO;

final class SystemRuleProvider extends Repository implements CommonRuleProvider 
{
    public function __construct(
        Database $db
    ){
        parent::__construct($db);
    }

    public function getRule(int $userId) : OperationChecker 
    {
        $builder = ScriptBuilder::withTable("roles_users", "ru");
        $builder->addSelect("ru.user_id")
            ->addSelect("COALESCE(bool_or(rp.sys_manage_accounts), false) as manage_accounts")
            ->addSelect("COALESCE(bool_or(rp.sys_manage_additionals), false) as manage_additionals")
            ->addSelect("COALESCE(bool_or(rp.sys_manage_ppl), false) as manage_ppl")
            ->addSelect("COALESCE(bool_or(rp.sys_manage_roles), false) as manage_roles")
            ->addJoin("INNER JOIN roles_permissions rp ON rp.role_id = ru.role_id AND rp.publication_id IS NOT DISTINCT FROM ru.publication_id")
            ->addWhere("ru.publication_id IS NULL")
            ->addWhere("ru.is_valid")
            ->addWhere("ru.user_id = :userId", [":userId" => ScriptParam::asInt($userId)])
            ->setGroup("ru.user_id");

        $stmt = $this->execute($builder->build(), $builder->getParams());
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false 
            ? SystemAccessRules::fromEmpty() 
            : SystemAccessRules::fromRow($row);
    }
}