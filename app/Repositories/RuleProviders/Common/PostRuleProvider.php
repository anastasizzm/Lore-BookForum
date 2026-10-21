<?php
declare(strict_types=1);

namespace App\Repositories\RuleProviders\Common;

use App\Models\Scripts\ScriptParam;
use App\Models\Access\Rules\OperationChecker;
use App\Models\Access\Rules\PostAccessRules;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\ScriptBuilder;

use App\Lib\Data\Database;
use PDO;

final class PostRuleProvider extends Repository implements CommonRuleProvider 
{
    public function __construct(
        Database $db
    ){
        parent::__construct($db);
    }

    public function getRule(int $userId, mixed $entityId) : OperationChecker 
    {
        $entityId = (int)$entityId;

        $builder = ScriptBuilder::withTable("roles_users", "ru");
        $builder->addSelect("\nru.user_id")
            ->addSelect("COALESCE(bool_or(rp.post_create), false) as create")
            ->addSelect("COALESCE(bool_or(rp.post_update), false) as update")
            ->addSelect("COALESCE(bool_or(rp.post_delete), false) as delete")
            ->addJoin("INNER JOIN roles_permissions rp ON rp.role_id = ru.role_id AND rp.publication_id = ru.publication_id")
            ->addWhere("ru.is_valid")
            ->addWhere("ru.user_id = :userId", [":userId" => ScriptParam::asInt($userId)])
            ->addWhere("ru.publication_id IS NULL")
            ->setGroup("ru.user_id");

        $stmt = $this->execute($builder->build(), $builder->getParams());
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false 
            ? PostAccessRules::fromEmpty() 
            : PostAccessRules::fromRow($row);
    }
}