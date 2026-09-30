<?php

namespace App\Models\Queries;

final class StatusQuery implements Query
{
    private readonly string $status;

    public function __construct(
        string $status
    ) {
        $this->status = strtolower($status);
    }

    public static function fromInput(array $input): self
    {
        return new self($input['status'] ?? '');
    }

    public function status() : string { return $this->status; }
}