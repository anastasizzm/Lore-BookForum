<?php
declare(strict_types=1);

namespace App\Services\Enrichers;

use App\Models\UserContext\PublicationContext;
use App\Models\UserContext\WithContext;
use App\Models\Enums\ServiceType;
use App\Models\Publications\PublicationShort;
use App\Models\Access\Rules\PublicationAccessRules;
use App\Repositories\Publications\PublicationsContextRepository;
use App\Repositories\RuleProviders\Concrete\RuleProvider;

final class PublicationContextEnricher
{
    public function __construct(
        private readonly PublicationsContextRepository $contexts,

        #[Inject(key: ServiceType::Publication)]
        private readonly RuleProvider $ruleProvider
    ) {}

    /**
     * @template TItem of PublicationShort
     * @param TItem[] $items
     * @return WithContext<TItem, PublicationContext>[]
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
            fn(PublicationShort $item) => new WithContext(
                $item,
                ($map[$item->id] ?? PublicationContext::fromEmpty())->withRules($permissions[$item->id] ?? PublicationAccessRules::fromEmpty()),
            ),
            $items,
        );
    }

    /**
     * @template TItem of PublicationShort
     * @param TItem $item
     * @return WithContext<TItem, PublicationContext>
     */
    public function enrichOne(PublicationShort $item, int $viewerId): WithContext
    {
        $map = $this->contexts->loadMap($viewerId, [$item->id]);
        $permissions = $this->ruleProvider->getRule($viewerId, $item->id);

        return new WithContext(
            $item,
            ($map[$item->id] ?? PublicationContext::fromEmpty())->withRules($permissions),
        );
    }

    /** @param PublicationShort[] $items @return int[] */
    private function ids(array $items): array
    {
        return array_map(fn(PublicationShort $i) => $i->id, $items);
    }
}