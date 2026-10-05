<?php
declare(strict_types=1);

namespace App\Models\UserContext;

/**
 * @template TItem    of object
 * @template TContext of UserContextInterface
 */
final readonly class WithContext
{
    /**
     * @param TItem    $item
     * @param TContext $context
     */
    public function __construct(
        public object               $item,
        public UserContextInterface $context,
    ) {}

    /** @return TItem */
    public function item(): object
    {
        return $this->item;
    }

    /** @return TContext */
    public function context(): UserContextInterface
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return $this->item->toArray() + $this->context->toArray();
    }
}