<?php
declare(strict_types=1);

namespace App\Services\Permissions\Ranks;
use App\Models\Enums\ServiceType;
use App\Attributes\Inject;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Repositories\RuleProviders\Concrete\RuleProvider;
use App\Repositories\Permissions\SystemPermissionsRepository;
use App\Lib\I18n\Translator;

final class SystemRoleRankService implements RankService
{
    private readonly RankService $service;

    public function __construct(
        #[Inject(key: ServiceType::System)]
        private readonly CommonRuleProvider $commonRuleProvider,
        private readonly SystemPermissionsRepository $repository,
        private readonly Translator $translator
    ){
        $this->service = new RoleRankService(
            $repository,
            $translator,
            commonRuleProvider: $commonRuleProvider,
        );
    }

    public function computeInsertRank(
        ?int $publicationId,
        ?int $aboveId,
        ?int $belowId,
        ?int $excludeRoleId = null
    ): string
    {
        return $this->service->computeInsertRank($publicationId, $aboveId, $belowId, $excludeRoleId);
    }
    public function canManageRole(int $userId, int $roleId, ?int $publicationId = null): bool
    {
        return $this->service->canManageRole($userid, $roleId, $publicationId);
    }

    /** @param array<int> $roleIds
     * @return array<bool>
     */
    public function canManageRoleMany(int $userId, array $roleIds, ?int $publicationId = null) : array
    {
        return $this->service->canManageRoleMany($userId, $roleIds, $publicationId);
    }

    public function canManageUser(int $userId, int $targetUserId, ?int $publicationId = null): bool
    {
        return $this->service->canManageUser($userid, $targetUserId, $publicationId);
    }

    /** @param array<int> $targetUserIds
     * @return array<bool>
     */
    public function canManageUserMany(int $userId, array $targetUserIds, ?int $publicationId = null) : array
    {
        return $this->service->canManageUserMany($userId, $targetUserIds, $publicationId);
    }

    public function rebalance(?int $publicationId = null): void
    {
        $this->service->rebalance($publicationId);
    }
}