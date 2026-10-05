<?php
declare(strict_types=1);

namespace App\Services\Enrichers;

use App\Models\UserContext\PostContext;
use App\Models\UserContext\WithContext;
use App\Models\Posts\Post;
use App\Repositories\Publications\PostsContextRepository;

final class PostContextEnricher
{
    public function __construct(
        private readonly PostsContextRepository $contexts,
    ) {}

    /**
     * @template TItem of Post
     * @param TItem[] $items
     * @return WithContext<TItem, PostContext>[]
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
            fn(Post $item) => new WithContext(
                $item,
                $map[$item->id] ?? new PostContext(),
            ),
            $items,
        );
    }

    /**
     * @template TItem of PostContext
     * @param TItem $item
     * @return WithContext<TItem, PostContext>
     */
    public function enrichOne(PostContext $item, ?int $viewerId): WithContext
    {
        $map = $viewerId !== null
            ? $this->contexts->loadMap($viewerId, [$item->id])
            : [];

        return new WithContext(
            $item,
            $map[$item->id] ?? new PostContext(),
        );
    }

    /** @param Post[] $items @return int[] */
    private function ids(array $items): array
    {
        return array_map(fn(Post $i) => $i->id, $items);
    }
}