<?php
declare(strict_types=1);

namespace App\Services\Enrichers;

use App\Models\UserContext\PublicationContext;
use App\Models\UserContext\WithContext;
use App\Models\Publications\PublicationShort;
use App\Repositories\Publications\PublicationsContextRepository;

final class PublicationContextEnricher
{
    public function __construct(
        private readonly PublicationsContextRepository $contexts,
    ) {}

    /**
     * @template TItem of PublicationShort
     * @param TItem[] $items
     * @return WithContext<TItem, PublicationContext>[]
     */
    public function enrich(array $items, ?int $viewerId): array
    {
        if ($items === []) {
            return [];
        }

        $map = $viewerId !== null
            ? $this->contexts->loadMap($viewerId, $this->ids($items))
            : [];

        return array_map(
            fn(PublicationShort $item) => new WithContext(
                $item,
                $map[$item->id] ?? new PublicationContext(),
            ),
            $items,
        );
    }

    /**
     * @template TItem of PublicationShort
     * @param TItem $item
     * @return WithContext<TItem, PublicationContext>
     */
    public function enrichOne(PublicationShort $item, ?int $viewerId): WithContext
    {
        $map = $viewerId !== null
            ? $this->contexts->loadMap($viewerId, [$item->id])
            : [];

        return new WithContext(
            $item,
            $map[$item->id] ?? new PublicationContext(),
        );
    }

    /** @param PublicationShort[] $items @return int[] */
    private function ids(array $items): array
    {
        return array_map(fn(PublicationShort $i) => $i->id, $items);
    }
}