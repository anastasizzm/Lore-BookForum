<?php
declare(strict_types=1);

namespace App\Services\Enrichers;

use App\Models\UserContext\PermissionsContext;
use App\Models\UserContext\WithContext;
use App\Models\Dto;
use App\Services\Permissions\Ranks\RankService;

final class UsersPermissionsContextEnricher
{
    public function __construct(
        private readonly RankService $rankService,
    ) {}

    /**
     * @template TItem of Dto
     * @param TItem[] $items
     * @return WithContext<TItem, PermissionsContext>[]
     */
    public function enrich(array $items, int $viewerId, ?int $publicationId = NULL): array
    {
        if ($items === []) {
            return [];
        }
        $ids = $this->ids($items);

        $map = $this->rankService->canManageUserMany($viewerId, $ids, $publicationId);
        return array_map(
            function (Dto $item) 
            {
                $mapped = $map[$item->id()] ?? NULL;
                return new WithContext(
                    $item,
                    $mapped === NULL ? PermissionsContext::fromEmpty() : new PermissionsContext($mapped)
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