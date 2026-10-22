<?php
declare(strict_types=1);

namespace App\Services\Enrichers;

use App\Attributes\Inject;
use App\Models\UserContext\PostContext;
use App\Models\UserContext\WithContext;
use App\Models\Posts\Post;
use App\Models\Enums\ServiceType;
use App\Models\Access\Rules\PostAccessRules;
use App\Repositories\Publications\PostsContextRepository;
use App\Repositories\RuleProviders\Concrete\RuleProvider;

final class PostContextEnricher
{
    public function __construct(
        private readonly PostsContextRepository $contexts,

        #[Inject(key: ServiceType::Post)]
        private readonly RuleProvider $ruleProvider
    ) {}

    /**
     * @template TItem of Post
     * @param TItem[] $items
     * @return WithContext<TItem, PostContext>[]
     */
    public function enrich(array $items, int $viewerId): array
    {
        if ($items === []) {
            return [];
        }
        $ids = $this->ids($items);

        $map = $this->contexts->loadMap($viewerId, $ids);
        $permissions = $this->ruleProvider->getRulesMany($viewerId, $ids);
        return array_map(
            fn(Post $item) => new WithContext(
                $item,
                ($map[$item->id] ?? PostContext::fromEmpty())->withRules($permissions[$item->id] ?? PostAccessRules::fromEmpty()),
            ),
            $items,
        );
    }

    /**
     * @template TItem of PostContext
     * @param TItem $item
     * @return WithContext<TItem, PostContext>
     */
    public function enrichOne(PostContext $item, int $viewerId): WithContext
    {
        $map = $this->contexts->loadMap($viewerId, [$item->id]);
        $permissions = $this->ruleProvider->getRule($viewerId, $item->id);

        return new WithContext(
            $item,
            ($map[$item->id] ?? PostContext::fromEmpty())->withRules($permissions),
        );
    }

    /** @param Post[] $items @return int[] */
    private function ids(array $items): array
    {
        return array_map(fn(Post $i) => $i->id, $items);
    }
}