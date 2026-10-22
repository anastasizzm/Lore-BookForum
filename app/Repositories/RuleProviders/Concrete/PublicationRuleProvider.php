<?php
declare(strict_types=1);

namespace App\Repositories\RuleProviders\Concrete;

use App\Models\Scripts\ScriptParam;
use App\Models\Access\Rules\OperationChecker;
use App\Models\Access\Rules\PublicationAccessRules;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\ScriptBuilder;

use App\Lib\Data\Database;
use PDO;

final class PublicationRuleProvider extends Repository implements RuleProvider 
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
        $builder->addSelect("p.id AS publication_id, ru.user_id")
            ->addSelect("COALESCE(bool_or(rp.pub_create),          false) OR p.owner_id = ru.user_id AS create")
            ->addSelect("COALESCE(bool_or(rp.pub_update_meta),     false) OR p.owner_id = ru.user_id AS update_meta")
            ->addSelect("COALESCE(bool_or(rp.pub_update_content),  false) OR p.owner_id = ru.user_id AS update_content")
            ->addSelect("COALESCE(bool_or(rp.pub_delete),          false) OR p.owner_id = ru.user_id AS delete")
            ->addSelect("COALESCE(bool_or(rp.pub_read), false) OR p.status = 'public' OR p.owner_id = ru.user_id AS read")
            ->addSelect("COALESCE(bool_or(rp.pub_manage_ppl),      false) OR p.owner_id = ru.user_id AS manage_ppl")
            ->addSelect("COALESCE(bool_or(rp.pub_manage_roles),    false) OR p.owner_id = ru.user_id AS manage_roles")
            ->addJoin("INNER JOIN roles_permissions rp
                        ON rp.role_id = ru.role_id
                        AND rp.publication_id IS NOT DISTINCT FROM ru.publication_id")
            ->addJoin("INNER JOIN publications p
                        ON p.id = :pubId
                        AND (ru.publication_id IS NULL OR ru.publication_id = p.id)", [':pubId' => ScriptParam::asInt($entityId)])
            ->addWhere("ru.is_valid")
            ->addWhere("ru.user_id = :userId", [":userId" => ScriptParam::asInt($userId)])
            ->setGroup("p.id, ru.user_id");

        $stmt = $this->execute($builder->build(), $builder->getParams());
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false 
            ? PublicationAccessRules::fromEmpty() 
            : PublicationAccessRules::fromRow($row);
    }

    public function getRulesMany(int $userId, array $entities) : array 
    {
        $ids = array_map('intval', $entities);

        if($ids = [])
        {
            return [];
        }

        $builder = ScriptBuilder::withTable("publications", "p");
        $builder->addSelect("p.id AS publication_id,\nru.user_id")
            ->addSelect("COALESCE(bool_or(rp.pub_create),          false) OR p.owner_id = ru.user_id AS create")
            ->addSelect("COALESCE(bool_or(rp.pub_update_meta),     false) OR p.owner_id = ru.user_id AS update_meta")
            ->addSelect("COALESCE(bool_or(rp.pub_update_content),  false) OR p.owner_id = ru.user_id AS update_content")
            ->addSelect("COALESCE(bool_or(rp.pub_delete),          false) OR p.owner_id = ru.user_id AS delete")
            ->addSelect("COALESCE(bool_or(rp.pub_read), false) OR p.status = 'public' OR p.owner_id = ru.user_id AS read")
            ->addSelect("COALESCE(bool_or(rp.pub_manage_ppl),      false) OR p.owner_id = ru.user_id AS manage_ppl")
            ->addSelect("COALESCE(bool_or(rp.pub_manage_roles),    false) OR p.owner_id = ru.user_id AS manage_roles")
            ->addJoin("INNER JOIN roles_users ru
                        ON ru.user_id = :userId 
                        AND ru.is_valid 
                        AND (ru.publication_id IS NULL OR ru.publication_id = p.id)", [":userId" => ScriptParam::asInt($userId)])
            ->addJoin("INNER JOIN roles_permissions rp
                        ON rp.role_id = ru.role_id
                        AND rp.publication_id IS NOT DISTINCT FROM ru.publication_id")
            ->addWhere("p.id = ANY(:pubIds::bigint[])", [':pubIds' => ScriptParam::asStr('{' . implode(',', $ids) . '}')])
            ->setGroup("p.id, ru.user_id");

        $byId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byId[(int) $row['publication_id']] = PublicationAccessRules::fromRow($row);
        }

        $rules = [];
        foreach ($ids as $id) {
            $rules[$id] = $byId[$id] ?? PublicationAccessRules::fromEmpty();
        }

        return $rules;
    }
}