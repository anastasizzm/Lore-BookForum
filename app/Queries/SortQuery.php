<?php

namespace App\Queries;

use UnitEnum;
use ValueError;
use ReflectionEnum;
use BackedEnum;

final class SortQuery implements Query
{
    private readonly string $sortString;

    public function __construct(
        string $sortString
    ) {
        $this->sortString = strtolower($sortString);
    }

    public static function fromInput(array $input): self
    {
        return new self($input['sort'] ?? '');
    }

    public function sortString() :string { return $this->sortString; }

    public function tryResolve(string $enumClass): ?UnitEnum
    {
        if (!enum_exists($enumClass)) {
            return null;
        }

        // 1. Backed enum — попытка через from() по value
        if (is_subclass_of($enumClass, BackedEnum::class)) {
            /** @var class-string<BackedEnum> $enumClass */
            try {
                return $enumClass::from($this->sortString);
            } catch (ValueError) {}
        }

        // 2. Поиск по имени case, без учёта регистра
        $ref = new ReflectionEnum($enumClass);

        foreach ($ref->getCases() as $case) {
            if (strcasecmp($case->getName(), $this->sortString) === 0) {
                return $case->getValue();
            }
        }

        return null;
    }
}