<?php
declare(strict_types=1);

namespace App\Repositories\RuleProviders\Concrete;

use App\Models\Scripts\ScriptParam;
use App\Models\Access\Rules\OperationChecker;
use App\Models\Access\Rules\PostAccessRules;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\ScriptBuilder;

use App\Lib\Data\Database;
use PDO;

final class PostRuleProvider extends Repository implements RuleProvider 
{
    public function __construct(
        Database $db
    ){
        parent::__construct($db);
    }

    public function getRule(int $userId, int|string $entityId) : OperationChecker 
    {
        $entityId = (int)$entityId;

        $builder = ScriptBuilder::withTable("roles_users", "ru");
        $builder->addSelect("p.id AS post_id, ru.user_id")
            ->addSelect("COALESCE(bool_or(rp.post_create), false) OR p.creator_id = ru.user_id AS create")
            ->addSelect("COALESCE(bool_or(rp.post_update), false) OR p.creator_id = ru.user_id AS update")
            ->addSelect("COALESCE(bool_or(rp.post_delete), false) OR p.creator_id = ru.user_id AS delete")
            ->addJoin("INNER JOIN comments p ON p.id = :postId", [':postId' => ScriptParam::asInt($entityId)])
            ->addJoin("INNER JOIN roles_permissions rp
                        ON rp.role_id = ru.role_id
                        AND rp.publication_id IS NOT DISTINCT FROM ru.publication_id")
            ->addWhere("ru.is_valid")
            ->addWhere("p.is_active")
            ->addWhere("ru.user_id = :userId", [":userId" => ScriptParam::asInt($userId)])
            ->addWhere("ru.publication_id IS NULL")
            ->setGroup("p.id, ru.user_id");

        $stmt = $this->execute($builder->build(), $builder->getParams());
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false 
            ? PostAccessRules::fromEmpty() 
            : PostAccessRules::fromRow($row);
    }

    public function getRulesMany(int $userId, array $entities) : array 
    {
        $ids = array_map('intval', $entities);

        if ($ids === []) {
            return [];
        }

        $builder = ScriptBuilder::withTable("comments", "p");
        $builder->addSelect("p.id AS post_id,\nru.user_id")
            ->addSelect("COALESCE(bool_or(rp.post_create), false) OR p.creator_id = ru.user_id AS create")
            ->addSelect("COALESCE(bool_or(rp.post_update), false) OR p.creator_id = ru.user_id AS update")
            ->addSelect("COALESCE(bool_or(rp.post_delete), false) OR p.creator_id = ru.user_id AS delete")
            ->addJoin("INNER JOIN roles_users ru
                        ON ru.user_id = :userId
                        AND ru.is_valid
                        AND ru.publication_id IS NULL", [":userId" => ScriptParam::asInt($userId)])
            ->addJoin("INNER JOIN roles_permissions rp
                        ON rp.role_id = ru.role_id
                        AND rp.publication_id IS NOT DISTINCT FROM ru.publication_id")
            ->addWhere("p.id = ANY(:postIds::bigint[])", [':postIds' => ScriptParam::asStr('{' . implode(',', $ids) . '}')])
            ->setGroup("p.id, ru.user_id");

        $stmt = $this->execute($builder->build(), $builder->getParams());

        $byId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byId[(int) $row['post_id']] = PostAccessRules::fromRow($row);
        }

        $rules = [];
        foreach ($ids as $id) {
            $rules[$id] = $byId[$id] ?? PostAccessRules::fromEmpty();
        }

        return $rules;
    }
}