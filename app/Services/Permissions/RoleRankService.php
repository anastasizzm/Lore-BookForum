<?php
declare(strict_types=1);

namespace App\Services\Permissions;

use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Repositories\Permissions\PermissionsRepository;
use App\Lib\I18n\Translator;
use App\Lib\LexoRank;
use App\Attributes\Inject;
use App\Models\Enums\ServiceType;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Repositories\RuleProviders\Concrete\RuleProvider;

final class RoleRankService
{
    private const MAX_RANK_LENGTH = 100;

    public function __construct(
        #[Inject(key: ServiceType::Publication)]
        private readonly RuleProvider $ruleProvider,
        #[Inject(key: ServiceType::Publication)]
        private readonly CommonRuleProvider $commonRuleProvider,
        private readonly PermissionsRepository $repository,
        private readonly Translator $translator
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

    public function canManageRole(int $userId, int $roleId, ?int $publicationId): bool
    {
        $localRank = $this->repository->getUserHighestRank($userId, $publicationId);
        if ($localRank !== null) {
            $targetRank = $this->repository->getRank($roleId, $publicationId);
            return $targetRank !== null && strcmp($localRank, $targetRank) >= 0;
        }

        $accessRules = $publicationId === null
            ? $this->commonRuleProvider->getRule($userId)
            : $this->ruleProvider->getRule($userId, $publicationId);

        return $accessRules->hasAny();
    }

    public function canManageUser(int $userId, int $targetUserId, ?int $publicationId): bool
    {
        if ($userId === $targetUserId) return false;

        $highestRole = $this->repository->getUserHighestRankRole($targetUserId, $publicationId);
        if ($highestRole === null) return false;

        return $this->canManageRole($userId, $highestRole, $publicationId);
    }

    public function rebalance(?int $publicationId = null): void
    {
        $this->repository->rebalanceRanks($publicationId);
    }
}