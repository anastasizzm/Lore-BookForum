<?php
declare(strict_types=1);

namespace App\Services\Permissions\Ranks;

interface RankService 
{
    public function computeInsertRank(
        ?int $publicationId,
        ?int $aboveId,
        ?int $belowId,
        ?int $excludeRoleId = null
    ): string;
    public function canManageRole(int $userId, int $roleId, ?int $publicationId = null): bool;
    /** @param array<int> $roleIds
     * @return array<bool>
     */
    public function canManageRoleMany(int $userId, array $roleIds, ?int $publicationId = null) : array;

    public function canManageUser(int $userId, int $targetUserId, ?int $publicationId = null): bool;
    /** @param array<int> $targetUserIds
     * @return array<bool>
     */
    public function canManageUserMany(int $userId, array $targetUserIds, ?int $publicationId = null) : array;

    public function rebalance(?int $publicationId = null): void;
}