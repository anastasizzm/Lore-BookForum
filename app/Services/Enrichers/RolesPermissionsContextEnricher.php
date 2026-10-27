<?php
declare(strict_types=1);

namespace App\Services\Enrichers;

use App\Models\UserContext\VersionedPermissionsContext;
use App\Models\UserContext\WithContext;
use App\Models\Dto;
use App\Repositories\Permissions\PermissionsRepository;
use App\Services\Permissions\Ranks\RankService;

final class RolesPermissionsContextEnricher
{
    public function __construct(
        private readonly RankService $rankService,
        private readonly PermissionsRepository $repository
    ) {}

    /**
     * @template TItem of Dto
     * @param TItem[] $items
     * @return WithContext<TItem, VersionedPermissionsContext>[]
     */
    public function enrich(array $items, int $viewerId, ?int $publicationId = NULL): array
    {
        if ($items === []) {
            return [];
        }
        $ids = $this->ids($items);

        $map = $this->rankService->canManageRoleMany($viewerId, $ids, $publicationId);
        $versions = $this->repository->getRolesVersions($ids, $publicationId);
        return array_map(
            function (Dto $item) 
            {
                $mapped = $map[$item->id()] ?? NULL;
                $version = $versions[$item->id()] ?? NULL;
                return new WithContext(
                    $item,
                    $mapped === NULL || $version === NULL 
                        ? VersionedPermissionsContext::fromEmpty() 
                        : new VersionedPermissionsContext($mapped, $version)
                );
            },
            $items,
        );
    }

    /** @param Dto[] $items @return int[] */
    private function ids(array $items): array
    {
        return array_map(fn(Dto $i) => $i->id(), $items);
    }
}