<?php
declare(strict_types=1);

namespace App\Repositories\Permissions;

use App\Repositories\Repository;
use App\Models\Scripts\ScriptParam;
use App\Models\Access\Permissions\SystemPermissions;
use App\Models\Access\Rules\PublicationAccessRules;
use App\Models\Access\Rules\PostAccessRules;
use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Access\Assignment\RolesUsersAssignment;
use App\Lib\I18n\Translator;
use App\Lib\Data\Database;
use App\Extensions\ScriptBuilders\ScriptBuilder;
use PDO;

final class SystemPermissionsRepository extends PermissionsRepository 
{
    public function __construct(
        Database $db,
        private readonly Translator $translator,
    ){
        parent::__construct($db, $translator);
    }

    /** @return array<SystemPermissions> */
    public function getRoles() : array 
    {
        $builder = ScriptBuilder::withTable("roles_permissions", "rp");
        $builder->addSelect("ur.id as r_id,
                \nur.id as role_id,
                \nur.color as r_color,
                \nur.is_system as r_is_system,
                \nur.created_at as r_created_at")
            ->addSelect("COALESCE(
                        MAX(CASE WHEN urt.code = :currentCulture THEN urt.title END),
                        MAX(CASE WHEN urt.code = :fallbackCulture THEN urt.title END)
                    ) as r_title", [':currentCulture' => ScriptParam::asStr($this->translator->locale()), ':fallbackCulture' => ScriptParam::asStr($this->translator->fallback())])
            ->addSelect("bool_or(rp.pub_create) as pub_create,
                \nbool_or(rp.pub_update_meta) as pub_update_meta,
                \nbool_or(rp.pub_update_content) as pub_update_content,
                \nbool_or(rp.pub_delete) as pub_delete,
                \nbool_or(rp.pub_read) as pub_read,
                \nbool_or(rp.pub_manage_ppl) as pub_manage_ppl,
                \nbool_or(rp.pub_manage_roles) as pub_manage_roles,
                \nbool_or(rp.post_create) as post_create,
                \nbool_or(rp.post_update) as post_update,
                \nbool_or(rp.post_delete) as post_delete,
                \nbool_or(rp.sys_manage_accounts) as sys_manage_accounts,
                \nbool_or(rp.sys_manage_additionals) as sys_manage_additionals,
                \nbool_or(rp.sys_manage_ppl) as sys_manage_ppl,
                \nbool_or(rp.sys_manage_roles) as sys_manage_roles")
            ->addJoin("INNER JOIN user_roles ur ON ur.id = rp.role_id")
            ->addJoin("INNER JOIN user_roles_translations urt ON urt.role_id = ur.id")
            ->addJoin("INNER JOIN languages l ON l.code = urt.code AND l.is_active")
            ->addWhere("rp.publication_id IS NULL")
            ->setGroup("ur.id")
            ->setOrder('MAX(rp.lexorank) COLLATE "C" DESC');

        $stmt = $this->execute($builder->build(), $builder->getParams());
        $items = array_map(
            static fn (array $row) => SystemPermissions::fromRow($row), 
            $stmt->fetchAll()
        );

        return $items;
    }
}