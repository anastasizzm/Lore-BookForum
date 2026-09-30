<?php

namespace App\Models\Queries;

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
}