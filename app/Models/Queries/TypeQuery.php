<?php

namespace App\Models\Queries;

final class TypeQuery implements Query
{
    private readonly string $type;

    public function __construct(
        string $type
    ) {
        $this->type = strtolower($type);
    }

    public static function fromInput(array $input): self
    {
        return new self($input['type'] ?? '');
    }

    public function hasData() : bool 
    {
        return !empty($this->type);
    }

    public static function default() : self 
    {
        return new self('');
    }

    public function type() : string { return $this->type; }
}