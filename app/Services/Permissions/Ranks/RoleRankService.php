<?php
declare(strict_types=1);

namespace App\Services\Permissions\Ranks;

use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Repositories\Permissions\PermissionsRepository;
use App\Lib\I18n\Translator;
use App\Lib\LexoRank;
use App\Attributes\Inject;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Repositories\RuleProviders\Concrete\RuleProvider;

class RoleRankService implements RankService
{
    private const MAX_RANK_LENGTH = 100;

    public function __construct(
        private readonly PermissionsRepository $repository,
        private readonly Translator $translator,
        private readonly ?RuleProvider $ruleProvider = NULL,
        private readonly ?CommonRuleProvider $commonRuleProvider = NULL,
    ) {}

    /**
     * @param int|null $aboveId  роль сверху (выше авторитет), null = вставить самым верхним
     * @param int|null $belowId  роль снизу (ниже авторитет), null = вставить самым нижним
     * @param int|null $excludeRoleId  перемещаемая роль (исключается из проверки соседей)
     */
    public function computeInsertRank(
        ?int $publicationId,
        ?int $aboveId,
        ?int $belowId,
        ?int $excludeRoleId = null
    ): string {
        $aboveRank = null;
        $belowRank = null;

        if ($aboveId !== null) {
            $aboveRank = $this->repository->getRank($aboveId, $publicationId);
            if ($aboveRank === null) {
                throw new ValidationException(['aboveId' => $this->translator->t('errors.common.not_found')]);
            }
        }

        if ($belowId !== null) {
            $belowRank = $this->repository->getRank($belowId, $publicationId);
            if ($belowRank === null) {
                throw new ValidationException(['belowId' => $this->translator->t('errors.common.not_found')]);
            }
        }

        if ($aboveRank !== null && $belowRank !== null && strcmp($aboveRank, $belowRank) <= 0) {
            throw new ValidationException(['aboveId' => $this->translator->t('errors.roles.rank_mismatch')]);
        }

        if (!$this->repository->areAdjacent($aboveRank, $belowRank, $publicationId, $excludeRoleId)) {
            throw new ConflictException($this->translator->t('errors.roles.rank_far'));
        }

        if ($aboveRank !== null && $belowRank === null) {
            return LexoRank::between(null, $aboveRank);
        }
        if ($aboveRank === null && $belowRank !== null) {
            return LexoRank::between($belowRank, null);
        }

        $newRank = LexoRank::between($belowRank, $aboveRank);
        if (strlen($newRank) > self::MAX_RANK_LENGTH) {
            $this->rebalance($publicationId);
            return $this->computeInsertRank($publicationId, $aboveId, $belowId, $excludeRoleId);
        }

        return $newRank;
    }

    public function canManageRole(int $userId, int $roleId, ?int $publicationId = null): bool
    {
        $localRank = $this->repository->getUserHighestRank($userId, $publicationId);
        if ($localRank !== null) {
            $targetRank = $this->repository->getRank($roleId, $publicationId);
            return $targetRank !== null && strcmp($localRank, $targetRank) >= 0;
        }

        $accessRules = $publicationId === null
            ? $this->commonRuleProvider?->getRule($userId)
            : $this->ruleProvider?->getRule($userId, $publicationId);

        return $accessRules?->hasAny() ?? false;
    }

    /** @param array<int> $roleIds
     * @return array<int, bool>
     */
    public function canManageRoleMany(int $userId, array $roleIds, ?int $publicationId = null) : array
    {
        if ($roleIds === []) return [];

        $localRank = $this->repository->getUserHighestRank($userId, $publicationId);

        if ($localRank === null) {
            $accessRules = $publicationId === null
                ? $this->commonRuleProvider?->getRule($userId)
                : $this->ruleProvider?->getRule($userId, $publicationId);

            return array_fill_keys($roleIds, $accessRules?->hasAny() ?? false);
        }

        $targetRanks = $this->repository->getRanksBatch($roleIds, $publicationId);

        $result = [];
        foreach ($roleIds as $roleId) {
            $targetRank = $targetRanks[$roleId] ?? null;
            $result[$roleId] = $targetRank !== null && strcmp($localRank, $targetRank) >= 0;
        }
        return $result;
    }

    public function canManageUser(int $userId, int $targetUserId, ?int $publicationId = null): bool
    {
        if ($userId === $targetUserId) return false;

        $highestRole = $this->repository->getUserHighestRankRole($targetUserId, $publicationId);
        if ($highestRole === null) return false;

        return $this->canManageRole($userId, $highestRole, $publicationId);
    }

    /** @param array<int> $targetUserIds
     * @return array<int, bool>
     */
    public function canManageUserMany(int $userId, array $targetUserIds, ?int $publicationId = null) : array
    {
        if ($targetUserIds === []) return [];

        $result = [];
        $toCheck = [];
        foreach ($targetUserIds as $targetUserId) {
            if ($targetUserId === $userId) {
                $result[$targetUserId] = false;
            } else {
                $toCheck[] = $targetUserId;
            }
        }

        if ($toCheck === []) return $result;

        $localRank   = $this->repository->getUserHighestRank($userId, $publicationId);
        $targetRanks = $this->repository->getUsersHighestRanksBatch($toCheck, $publicationId);

        if ($localRank === null) {
            $accessRules = $publicationId === null
                ? $this->commonRuleProvider?->getRule($userId)
                : $this->ruleProvider?->getRule($userId, $publicationId);
            $canManageAny = $accessRules?->hasAny() ?? false;

            foreach ($toCheck as $targetUserId) {
                $result[$targetUserId] = isset($targetRanks[$targetUserId]) && $canManageAny;
            }
            return $result;
        }

        foreach ($toCheck as $targetUserId) {
            $targetRank = $targetRanks[$targetUserId] ?? null;
            $result[$targetUserId] = $targetRank !== null && strcmp($localRank, $targetRank) >= 0;
        }
        return $result;
    }

    public function rebalance(?int $publicationId = null): void
    {
        $this->repository->rebalanceRanks($publicationId);
    }
}