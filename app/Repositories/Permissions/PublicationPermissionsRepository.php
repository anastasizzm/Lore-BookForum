<?php
declare(strict_types=1);

namespace App\Repositories\Permissions;

use App\Repositories\Repository;
use App\Models\Scripts\ScriptParam;
use App\Models\Access\Rules\PublicationAccessRules;
use App\Models\Access\Rules\PostAccessRules;
use App\Models\Access\Rules\SystemAccessRules;
use App\Lib\I18n\Translator;
use App\Lib\Data\Database;
use PDO;

final class PublicationPermissionsRepository extends PermissionsRepository 
{
    public function __construct(
        Database $db,
        Translator $translator
    ){
        parent::__construct($db, $translator);
    }

    /** @return array<PublicationPermissions> */
    public function getRoles(int $publicationId) : array 
    {
        $sql = 'SELECT rp.role_id,
                    ur.id as r_id,
                    ur.color as r_color,
                    ur.is_system as r_is_system,
                    rp.pub_create,
                    rp.pub_updateMeta,
                    rp.pub_updateContent,
                    rp.pub_delete,
                    rp.pub_read,
                    rp.pub_manage_ppl,
                    rp.pub_manage_roles
                FROM roles_permissions rp
                INNER JOIN user_roles ur ON rp.role_id = ur.id
                WHERE rp.publication_id = :pubId
                ORDER BY rp.lexorank COLLATE "C" DESC';

        $stmt = $this->execute($sql, [':pubId' => $publicationId]);
        $items = array_map(
            static fn (array $row) => PublicationPermissions::fromRow($row), 
            $stmt->fetchAll()
        );

        return $items;
    }
}