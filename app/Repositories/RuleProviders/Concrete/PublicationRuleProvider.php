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

    public function getRule(int $userId, int|string|object $entityId) : OperationChecker 
    {
        $entityId = (int)$entityId;

        $builder = ScriptBuilder::withTable("roles_users", "ru");
        $builder->addSelect("p.id as publication_id,\nru.user_id")
            ->addSelect("COALESCE(bool_or(rp.pub_create), false) OR p.owner_id = ru.user_id as create")
            ->addSelect("COALESCE(bool_or(rp.pub_update_meta), false) OR p.owner_id = ru.user_id as update_meta")
            ->addSelect("COALESCE(bool_or(rp.pub_update_content), false) OR p.owner_id = ru.user_id as update_content")
            ->addSelect("COALESCE(bool_or(rp.pub_delete), false) OR p.owner_id = ru.user_id as delete")
            ->addSelect("COALESCE(bool_or(rp.pub_read), false) OR p.status = 'public' OR p.owner_id = ru.user_id as read")
            ->addSelect("COALESCE(bool_or(rp.pub_manage_ppl), false) OR p.owner_id = ru.user_id as manage_ppl")
            ->addSelect("COALESCE(bool_or(rp.pub_manage_roles), false) OR p.owner_id = ru.user_id as manage_roles")
            ->addWith("publication", "SELECT id, owner_id, status FROM publications WHERE id = :pubId", false, [":pubId" => ScriptParam::asInt($entityId)])
            ->addJoin("INNER JOIN roles_permissions rp ON rp.role_id = ru.role_id AND rp.publication_id = ru.publication_id")
            ->addJoin("INNER JOIN publication p ON ru.publication_id IS NULL OR ru.publication_id = p.id")
            ->addWhere("ru.is_valid")
            ->addWhere("ru.user_id = :userId", [":userId" => ScriptParam::asInt($userId)])
            ->setGroup("p.id, ru.user_id");

        $stmt = $this->execute($builder->build(), $builder->getParams());
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false 
            ? PublicationAccessRules::fromEmpty() 
            : PublicationAccessRules::fromRow($row);
    }
}