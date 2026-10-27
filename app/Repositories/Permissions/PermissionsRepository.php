<?php
declare(strict_types=1);

namespace App\Repositories\Permissions;

use App\Repositories\Repository;
use App\Models\Scripts\ScriptParam;
use App\Models\Users\UserShortData;
use App\Models\Enums\UsersSortBy;
use App\Models\Additional\UserRole;
use App\Models\Access\Rules\PublicationAccessRules;
use App\Models\Access\Rules\PostAccessRules;
use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Access\Assignment\RolesUser;
use App\Models\Access\Assignment\RolesUsersAssignment;
use App\Lib\I18n\Translator;
use App\Lib\Data\Database;
use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\Additional\RolesScriptDirector;
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

    public function getRolesVersions(array $roles, ?int $publicationId) : array
    {
        if ($roles == []) return [];

        $builder = ScriptBuilder::withTable("roles_permissions");
        $builder->addSelect("role_id, version")
            ->addWhere("role_id = ANY(:roleIds::int[])", [':roleIds' => ScriptParam::asStr('{' . implode(', ', $roles) . '}')]);
        
        if($publicationId === NULL) $innerBuilder->addWhere("publication_id IS NULL");
        else $innerBuilder->addWhere("publication_id = :pubId", [':pubId' => ScriptParam::asInt($publicationId)]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['role_id']] = (int)$row['version'];
        }
        return $result;
    }

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

        $params = [
            ":roleId"           => ScriptParam::asInt($roleId),
            ":pubId"            => ScriptParam::asInt($publicationId),
            ":pubCreate"        => ScriptParam::asBool($pubRules?->create ?? false),
            ":pubUpdateMeta"    => ScriptParam::asBool($pubRules?->updateMeta ?? false),
            ":pubUpdateContent" => ScriptParam::asBool($pubRules?->updateContent ?? false),
            ":pubDelete"        => ScriptParam::asBool($pubRules?->delete ?? false),
            ":pubRead"          => ScriptParam::asBool($pubRules?->read ?? false),
            ":pubManageP"       => ScriptParam::asBool($pubRules?->managePeople ?? false),
            ":pubManageR"       => ScriptParam::asBool($pubRules?->manageRoles ?? false),
            ":postCreate"       => ScriptParam::asBool($postRules?->create ?? false),
            ":postUpdate"       => ScriptParam::asBool($postRules?->update ?? false),
            ":postDelete"       => ScriptParam::asBool($postRules?->delete ?? false),
            ":sysManageAcc"     => ScriptParam::asBool($sysRules?->manageAccounts ?? false),
            ":sysManageAdd"     => ScriptParam::asBool($sysRules?->manageAdditionals ?? false),
            ":sysManageP"       => ScriptParam::asBool($sysRules?->managePeople ?? false),
            ":sysManageR"       => ScriptParam::asBool($sysRules?->manageRoles ?? false),
            ":lexorank"         => ScriptParam::asStr($lexorank),
        ];

        $stmt = $this->execute($sql, $params);
        return (int)$stmt->fetchColumn();
    }

    public function editRole(
        int $roleId,
        ?int $publicationId,
        int $expectedVersion,
        ?PublicationAccessRules $pubRules = null,
        ?PostAccessRules $postRules = null,
        ?SystemAccessRules $sysRules = null,
    ): ?int {
        if ($pubRules === null && $postRules === null && $sysRules === null) {
            throw new \InvalidArgumentException('editRole requires at least one rules object');
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
            AND version = :expectedVersion
            RETURNING version
        ";

        $stmt = $this->execute($sql, [
            ':roleId'           => ScriptParam::asInt($roleId),
            ':pubId'            => ScriptParam::asInt($publicationId),
            ':expectedVersion'  => ScriptParam::asInt($expectedVersion),
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
            ':lexorank'        => ScriptParam::asStr($lexorank),
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

    /** @return array<RolesUser> */
    public function getUsers(
        ?int $publicationId, 
        UsersSortBy $sort,
        int $page, 
        int $pageSize,
        array $includeObjects) : array 
    {
        $innerBuilder = ScriptBuilder::withTable("roles_users");
        $innerBuilder->addSelect("user_id")
            ->addWhere("is_valid")
            ->setGroup("user_id");

        if($publicationId === NULL) $innerBuilder->addWhere("publication_id IS NULL");
        else $innerBuilder->addWhere("publication_id = :pubId", [':pubId' => ScriptParam::asInt($publicationId)]);

        $builder = ScriptBuilder::withTable("users_ids", "ui");
        $builder->addWithBuilder("users_ids", $innerBuilder)
            ->addSelect("u.id,
                \nu.username,
                \np.name,
                \np.surname,
                \np.avatar")
            ->addJoin("INNER JOIN users u ON u.id = ui.user_id")
            ->addJOin("INNER JOIN profiles p ON p.user_id = u.id")
            ->setOrder(
                match($sort)
                {
                    UsersSortBy::Username => 'u.username',
                    UsersSortBy::Name => 'p.name, p.surname',
                    default => 'u.id'
                }
            )
            ->setLimit($pageSize+1, ($page-1)*$pageSize);


        $stmt = $this->execute($builder->build(), $builder->getParams());
        $items = array_map(
            static fn (array $row) => RolesUser::fromRow($row), 
            $stmt->fetchAll()
        );

        if (!in_array('roles', $includeObjects)) return $items;

        $ids = array_map(static fn(RolesUser $user) => $user->id, $items);
        $roles = $this->acceptUsersRoles($ids, $publicationId);
        
        $result = [];
        foreach($items as $user)
            $result[] = $user->withRoles($roles[$user->id]);

        return $result;
    }

    /** @return array<UserRole> */
    private function acceptUsersRoles(array $usersIds, ?int $publicationId) : array 
    {
        $builder = ScriptBuilder::withTable("roles_users", "ru");
        $builder->addSelect("ru.user_id,
                \nur.id,
                \nur.color,
                \nur.is_system,
                \nur.created_at")
            ->addSelect("COALESCE(
                        MAX(CASE WHEN urt.code = :currentCulture THEN urt.title END),
                        MAX(CASE WHEN urt.code = :fallbackCulture THEN urt.title END)
                    ) as title", [':currentCulture' => ScriptParam::asStr($this->translator->locale()), ':fallbackCulture' => ScriptParam::asStr($this->translator->fallback())])
            ->addJoin("INNER JOIN user_roles ur ON ur.id = ru.role_id")
            ->addJoin("INNER JOIN roles_permissions rp ON rp.role_id = ru.role_id AND rp.publication_id IS NOT DISTINCT FROM ru.publication_id")
            ->addJoin("INNER JOIN user_roles_translations urt ON ur.id = urt.role_id")
            ->addJoin("INNER JOIN languages l ON l.code = urt.code AND l.is_active")
            ->addWhere("ru.is_valid")
            ->addWhere("ru.user_id = ANY(:userIds::bigint[])", [':userIds' => ScriptParam::asStr('{' . implode(', ', $usersIds) . '}')])
            ->setGroup("ru.user_id, ur.id")
            ->setOrder('ru.user_id, MAX(rp.lexorank) COLLATE "C" DESC');

        if($publicationId === NULL) $builder->addWhere("ru.publication_id IS NULL");
        else $builder->addWhere("ru.publication_id = :pubId", [':pubId' => ScriptParam::asInt($publicationId)]);

        $stmt = $this->execute($builder->build(), $builder->getParams());
        
        $result = [];
        foreach($stmt->fetchAll() as $row)
        {
            $userId = (int)$row['user_id'];
            $result[$userId][] = UserRole::fromRow($row);
        }
        
        return $result;
    }

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

    public function removeUser(int $userId, ?int $roleId, ?int $publicationId): array
    {
        $sql = "UPDATE roles_users
                SET valid_to = NOW(), version = version + 1
                WHERE user_id = :userId
                  AND (:roleIdCheck IS NULL OR role_id = :roleIdValue)
                  AND publication_id IS NOT DISTINCT FROM :pubId
                  AND is_valid
                RETURNING version";

        $stmt = $this->execute($sql, [
            ':userId'      => ScriptParam::asInt($userId),
            ':pubId'       => ScriptParam::asInt($publicationId),
            ':roleIdCheck' => ScriptParam::asInt($roleId),
            ':roleIdValue' => ScriptParam::asInt($roleId),
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<RolesUsersAssignment> */
    public function getUserRoles(?int $publicationId, int $userId, int $page, int $pageSize) : array
    {
        $builder = ScriptBuilder::withTable("roles_permissions", "rp");
        $builder->addSelect("ru.id,
                \nru.role_id,
                \nru.valid_from as created_at,
                \nur.color as color")
            ->addSelect("COALESCE(
                        MAX(CASE WHEN urt.code = :currentCulture THEN urt.title END),
                        MAX(CASE WHEN urt.code = :fallbackCulture THEN urt.title END)
                    ) as title", [':currentCulture' => ScriptParam::asStr($this->translator->locale()), ':fallbackCulture' => ScriptParam::asStr($this->translator->fallback())])
            ->addJoin("INNER JOIN roles_users ru ON ru.role_id = rp.role_id AND ru.publication_id IS NOT DISTINCT FROM rp.publication_id")
            ->addJoin("INNER JOIN user_roles ur ON ur.id = rp.role_id")
            ->addJoin("INNER JOIN user_roles_translations urt ON urt.role_id = ur.id")
            ->addJoin("INNER JOIN languages l ON l.code = urt.code AND l.is_active")
            ->addWhere("ru.is_valid")
            ->addWhere("ru.user_id = :userId", [':userId' => ScriptParam::asInt($userId)])
            ->setGroup("ru.id, ur.id")
            ->setOrder('MAX(rp.lexorank) COLLATE "C" DESC')
            ->setLimit($pageSize+1, ($page-1)*$pageSize);

        if($publicationId === NULL) $builder->addWhere("rp.publication_id IS NULL");
        else $builder->addWhere("rp.publication_id = :pubId", [':pubId' => ScriptParam::asInt($publicationId)]);

        $stmt = $this->execute($builder->build(), $builder->getParams());
        $items = array_map(
            static fn (array $row) => RolesUsersAssignment::fromRow($row), 
            $stmt->fetchAll()
        );

        return $items;
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
                ':lexorank' => ScriptParam::asStr($lexorank),
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
                ':lexorank' => ScriptParam::asStr($lexorank),
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

    /**
     * @param int[] $roleIds
     * @return array<int, string>  role_id => lexorank. Отсутствующие в результате id не имеют роли.
     */
    public function getRanksBatch(array $roleIds, ?int $publicationId): array
    {
        if ($roleIds === []) return [];

        $ids = implode(',', array_map('intval', $roleIds));

        $stmt = $this->execute(
            'SELECT role_id, lexorank FROM roles_permissions
            WHERE role_id = ANY(:roleIds::bigint[])
            AND publication_id IS NOT DISTINCT FROM :pubId',
            [
                ':pubId'   => ScriptParam::asInt($publicationId),
                ':roleIds' => ScriptParam::asStr('{' . $ids . '}'),
            ]
        );

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['role_id']] = (string)$row['lexorank'];
        }
        return $result;
    }

    /**
     * @param int[] $userIds
     * @return array<int, string>  user_id => lexorank
     */
    public function getUsersHighestRanksBatch(array $userIds, ?int $publicationId): array
    {
        if ($userIds === []) return [];

        $ids = implode(',', array_map('intval', $userIds));

        $stmt = $this->execute(
            'SELECT DISTINCT ON (ru.user_id) ru.user_id, rp.lexorank
            FROM roles_users ru
            INNER JOIN roles_permissions rp
                ON rp.role_id = ru.role_id
                AND rp.publication_id IS NOT DISTINCT FROM ru.publication_id
            WHERE ru.user_id = ANY(:userIds::bigint[])
            AND ru.publication_id IS NOT DISTINCT FROM :pubId
            AND ru.is_valid
            ORDER BY ru.user_id, rp.lexorank COLLATE "C" DESC',
            [
                ':pubId'   => ScriptParam::asInt($publicationId),
                ':userIds' => ScriptParam::asStr('{' . $ids . '}'),
            ]
        );

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['user_id']] = (string)$row['lexorank'];
        }
        return $result;
    }

    public function areAdjacent(
        ?string $aboveLexorank,
        ?string $belowLexorank,
        ?int $publicationId,
        ?int $excludeRoleId = null
    ): bool {
        $builder = ScriptBuilder::withTable("roles_permissions");
        $builder->addSelect("1")
            ->addWhere("publication_id IS NOT DISTINCT FROM :pubId", [':pubId' => ScriptParam::asInt($publicationId)])
            ->setLimit(1);

        if ($aboveLexorank !== null) {
            $builder->addWhere('lexorank < :aboveLexorank COLLATE "C"', [':aboveLexorank' => ScriptParam::asStr($aboveLexorank)]);
        }

        if ($belowLexorank !== null) {
            $builder->addWhere('lexorank > :belowLexorank COLLATE "C"', [':belowLexorank' => ScriptParam::asStr($belowLexorank)]);
        }

        if ($excludeRoleId !== null) {
            $builder->addWhere('role_id <> :excludeRoleId', [':excludeRoleId' => ScriptParam::asStr($excludeRoleId)]);
        }

        $stmt = $this->execute($builder->build(), $builder->getParams());
        return $stmt->fetchColumn() === false;
    }
}