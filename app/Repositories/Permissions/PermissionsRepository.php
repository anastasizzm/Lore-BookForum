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

class PermissionsRepository extends Repository
{
    private const INSERT_COLUMNS = '
        (role_id, publication_id,
        pub_create, pub_update_meta, pub_update_content, pub_delete,
        pub_read, pub_manage_ppl, pub_manage_roles,
        post_create, post_update, post_delete,
        sys_manage_accounts, sys_manage_additionals, sys_manage_ppl, sys_manage_roles,
        lexorank)';

    public function __construct(Database $db, private readonly Translator $translator)
    {
        parent::__construct($db);
    }

    // --- Role

    public function addRole(
        int $roleId,
        ?int $publicationId,
        string $lexorank,
        ?PublicationAccessRules $pubRules = null,
        ?PostAccessRules $postRules = null,
        ?SystemAccessRules $sysRules = null,
    ): int {
        if ($pubRules === null && $postRules === null && $sysRules === null) {
            throw new \InvalidArgumentException('addRole requires at least one rules object');
        }

        $sql = "INSERT INTO roles_permissions " . self::INSERT_COLUMNS . "
                VALUES
                (:roleId, :pubId,
                :pubCreate, :pubUpdateMeta, :pubUpdateContent, :pubDelete,
                :pubRead, :pubManageP, :pubManageR,
                :postCreate, :postUpdate, :postDelete,
                :sysManageAcc, :sysManageAdd, :sysManageP, :sysManageR,
                :lexorank)
                RETURNING version";

        $stmt = $this->execute($sql, $this->roleParams(
            ':r', $roleId, $publicationId, $lexorank, $pubRules, $postRules, $sysRules
        ));

        return (int)$stmt->fetchColumn();
    }

    public function editRole(
        int $roleId,
        ?int $publicationId,
        ?PublicationAccessRules $pubRules = null,
        ?PostAccessRules $postRules = null,
        ?SystemAccessRules $sysRules = null,
    ): ?int {
        if ($pubRules === null && $postRules === null && $sysRules === null) {
            throw new \InvalidArgumentException(
                'editRole requires at least one rules object; passing all null would wipe every permission bit'
            );
        }

        $sql = "
            UPDATE roles_permissions SET
                pub_create             = :pubCreate,
                pub_update_meta        = :pubUpdateMeta,
                pub_update_content     = :pubUpdateContent,
                pub_delete             = :pubDelete,
                pub_read               = :pubRead,
                pub_manage_ppl         = :pubManageP,
                pub_manage_roles       = :pubManageR,
                post_create            = :postCreate,
                post_update            = :postUpdate,
                post_delete            = :postDelete,
                sys_manage_accounts    = :sysManageAcc,
                sys_manage_additionals = :sysManageAdd,
                sys_manage_ppl         = :sysManageP,
                sys_manage_roles       = :sysManageR,
                version                = version + 1
            WHERE role_id = :roleId
              AND publication_id IS NOT DISTINCT FROM :pubId
            RETURNING version
        ";

        $stmt = $this->execute($sql, [
            ':roleId'           => ScriptParam::asInt($roleId),
            ':pubId'            => ScriptParam::asInt($publicationId),
            ':pubCreate'        => ScriptParam::asBool($pubRules?->create ?? false),
            ':pubUpdateMeta'    => ScriptParam::asBool($pubRules?->updateMeta ?? false),
            ':pubUpdateContent' => ScriptParam::asBool($pubRules?->updateContent ?? false),
            ':pubDelete'        => ScriptParam::asBool($pubRules?->delete ?? false),
            ':pubRead'          => ScriptParam::asBool($pubRules?->read ?? false),
            ':pubManageP'       => ScriptParam::asBool($pubRules?->managePeople ?? false),
            ':pubManageR'       => ScriptParam::asBool($pubRules?->manageRoles ?? false),
            ':postCreate'       => ScriptParam::asBool($postRules?->create ?? false),
            ':postUpdate'       => ScriptParam::asBool($postRules?->update ?? false),
            ':postDelete'       => ScriptParam::asBool($postRules?->delete ?? false),
            ':sysManageAcc'     => ScriptParam::asBool($sysRules?->manageAccounts ?? false),
            ':sysManageAdd'     => ScriptParam::asBool($sysRules?->manageAdditionals ?? false),
            ':sysManageP'       => ScriptParam::asBool($sysRules?->managePeople ?? false),
            ':sysManageR'       => ScriptParam::asBool($sysRules?->manageRoles ?? false),
        ]);

        $row = $stmt->fetchColumn();
        return $row === false ? null : (int)$row;
    }

    /** @return array<string, ScriptParam> */
    private function roleParams(
        string $p,
        int $roleId,
        ?int $publicationId,
        string $lexorank,
        ?PublicationAccessRules $pubRules,
        ?PostAccessRules $postRules,
        ?SystemAccessRules $sysRules,
    ): array {
        return [
            "{$p}roleId"           => ScriptParam::asInt($roleId),
            "{$p}pubId"            => ScriptParam::asInt($publicationId),
            "{$p}pubCreate"        => ScriptParam::asBool($pubRules?->create ?? false),
            "{$p}pubUpdateMeta"    => ScriptParam::asBool($pubRules?->updateMeta ?? false),
            "{$p}pubUpdateContent" => ScriptParam::asBool($pubRules?->updateContent ?? false),
            "{$p}pubDelete"        => ScriptParam::asBool($pubRules?->delete ?? false),
            "{$p}pubRead"          => ScriptParam::asBool($pubRules?->read ?? false),
            "{$p}pubManageP"       => ScriptParam::asBool($pubRules?->managePeople ?? false),
            "{$p}pubManageR"       => ScriptParam::asBool($pubRules?->manageRoles ?? false),
            "{$p}postCreate"       => ScriptParam::asBool($postRules?->create ?? false),
            "{$p}postUpdate"       => ScriptParam::asBool($postRules?->update ?? false),
            "{$p}postDelete"       => ScriptParam::asBool($postRules?->delete ?? false),
            "{$p}sysManageAcc"     => ScriptParam::asBool($sysRules?->manageAccounts ?? false),
            "{$p}sysManageAdd"     => ScriptParam::asBool($sysRules?->manageAdditionals ?? false),
            "{$p}sysManageP"       => ScriptParam::asBool($sysRules?->managePeople ?? false),
            "{$p}sysManageR"       => ScriptParam::asBool($sysRules?->manageRoles ?? false),
            "{$p}lexorank"         => ScriptParam::asString($lexorank),
        ];
    }

    /**
     * @return int|null
     */
    public function editLexorank(
        int $roleId,
        ?int $publicationId,
        string $lexorank,
        int $expectedVersion
    ): ?int {
        $sql = "UPDATE roles_permissions
                SET lexorank = :lexorank,
                    version  = version + 1
                WHERE role_id = :roleId
                  AND publication_id IS NOT DISTINCT FROM :pubId
                  AND version = :expectedVersion
                RETURNING version";

        $stmt = $this->execute($sql, [
            ':roleId'          => ScriptParam::asInt($roleId),
            ':pubId'           => ScriptParam::asInt($publicationId),
            ':lexorank'        => ScriptParam::asString($lexorank),
            ':expectedVersion' => ScriptParam::asInt($expectedVersion),
        ]);

        $row = $stmt->fetchColumn();
        return $row === false ? null : (int)$row;
    }

    public function removeRole(int $roleId, ?int $publicationId): void
    {
        $this->execute(
            "DELETE FROM roles_permissions
             WHERE role_id = :roleId AND publication_id IS NOT DISTINCT FROM :pubId",
            [
                ':roleId' => ScriptParam::asInt($roleId),
                ':pubId'  => ScriptParam::asInt($publicationId),
            ]
        );
    }

    // --- User

    public function addUser(int $userId, int $roleId, ?int $publicationId, int $granterId): int
    {
        $sql = "INSERT INTO roles_users (user_id, publication_id, role_id, granted_by_id)
                VALUES (:userId, :pubId, :roleId, :granter)
                RETURNING version";

        $stmt = $this->execute($sql, [
            ':userId'  => ScriptParam::asInt($userId),
            ':pubId'   => ScriptParam::asInt($publicationId),
            ':roleId'  => ScriptParam::asInt($roleId),
            ':granter' => ScriptParam::asInt($granterId),
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * @return array<int>  Версии всех отозванных записей. Пусто = ничего не нашли.
     */
    public function removeUser(int $userId, ?int $roleId, ?int $publicationId): array
    {
        $sql = "UPDATE roles_users
                SET valid_to = NOW(), version = version + 1
                WHERE user_id = :userId
                  AND (:roleIdCheck IS NULL OR role_id = :roleIdValue)
                  AND publication_id IS NOT DISTINCT FROM :pubId
                  AND valid_to IS NULL
                RETURNING version";

        $stmt = $this->execute($sql, [
            ':userId'      => ScriptParam::asInt($userId),
            ':pubId'       => ScriptParam::asInt($publicationId),
            ':roleIdCheck' => ScriptParam::asInt($roleId),
            ':roleIdValue' => ScriptParam::asInt($roleId),
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // --- Rank / lexorank queries (все с NOT DISTINCT FROM)

    public function getHigherRank(string $lexorank, ?int $publicationId = null): ?string
    {
        $stmt = $this->execute(
            'SELECT lexorank FROM roles_permissions
             WHERE publication_id IS NOT DISTINCT FROM :pubId
               AND lexorank > :lexorank COLLATE "C"
             ORDER BY lexorank COLLATE "C" ASC
             LIMIT 1',
            [
                ':pubId'    => ScriptParam::asInt($publicationId),
                ':lexorank' => ScriptParam::asString($lexorank),
            ]
        );
        $row = $stmt->fetchColumn();
        return $row === false ? null : (string)$row;
    }

    public function getLowerRank(string $lexorank, ?int $publicationId = null): ?string
    {
        $stmt = $this->execute(
            'SELECT lexorank FROM roles_permissions
             WHERE publication_id IS NOT DISTINCT FROM :pubId
               AND lexorank < :lexorank COLLATE "C"
             ORDER BY lexorank COLLATE "C" DESC
             LIMIT 1',
            [
                ':pubId'    => ScriptParam::asInt($publicationId),
                ':lexorank' => ScriptParam::asString($lexorank),
            ]
        );
        $row = $stmt->fetchColumn();
        return $row === false ? null : (string)$row;
    }

    public function getRank(int $roleId, ?int $publicationId = null): ?string
    {
        $stmt = $this->execute(
            'SELECT lexorank FROM roles_permissions
             WHERE role_id = :roleId
               AND publication_id IS NOT DISTINCT FROM :pubId
             LIMIT 1',
            [
                ':pubId'  => ScriptParam::asInt($publicationId),
                ':roleId' => ScriptParam::asInt($roleId),
            ]
        );
        $row = $stmt->fetchColumn();
        return $row === false ? null : (string)$row;
    }

    public function getHighestRank(?int $publicationId = null): ?string
    {
        $row = $this->getRankRow($publicationId, true);
        return $row === null ? null : $row[1];
    }

    public function getHighestRankRole(?int $publicationId = null): ?int
    {
        $row = $this->getRankRow($publicationId, true);
        return $row === null ? null : (int)$row[0];
    }

    public function getLowestRank(?int $publicationId = null): ?string
    {
        $row = $this->getRankRow($publicationId);
        return $row === null ? null : $row[1];
    }

    public function getLowestRankRole(?int $publicationId = null): ?int
    {
        $row = $this->getRankRow($publicationId);
        return $row === null ? null : (int)$row[0];
    }

    /** @return array{0:int, 1:string}|null */
    private function getRankRow(?int $publicationId, bool $orderDesc = false): ?array
    {
        $order = $orderDesc ? 'DESC' : 'ASC';
        $stmt = $this->execute(
            'SELECT role_id, lexorank FROM roles_permissions
             WHERE publication_id IS NOT DISTINCT FROM :pubId
             ORDER BY lexorank COLLATE "C" ' . $order . '
             LIMIT 1',
            [':pubId' => ScriptParam::asInt($publicationId)]
        );
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row === false ? null : [(int)$row[0], (string)$row[1]];
    }

    public function getUserHighestRank(int $userId, ?int $publicationId): ?string
    {
        $row = $this->getUserHighestRankRow($userId, $publicationId);
        return $row === null ? null : $row[1];
    }

    public function getUserHighestRankRole(int $userId, ?int $publicationId): ?int
    {
        $row = $this->getUserHighestRankRow($userId, $publicationId);
        return $row === null ? null : (int)$row[0];
    }

    /** @return array{0:int, 1:string}|null */
    private function getUserHighestRankRow(int $userId, ?int $publicationId): ?array
    {
        $stmt = $this->execute(
            'SELECT rp.role_id, rp.lexorank
             FROM roles_users ru
             INNER JOIN roles_permissions rp
                 ON ru.role_id = rp.role_id
                 AND ru.publication_id IS NOT DISTINCT FROM rp.publication_id
             WHERE ru.user_id = :userId
               AND ru.publication_id IS NOT DISTINCT FROM :pubId
               AND ru.valid_to IS NULL
             ORDER BY rp.lexorank COLLATE "C" DESC
             LIMIT 1',
            [
                ':pubId'  => ScriptParam::asInt($publicationId),
                ':userId' => ScriptParam::asInt($userId),
            ]
        );
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row === false ? null : [(int)$row[0], (string)$row[1]];
    }
}