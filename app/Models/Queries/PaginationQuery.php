<?php
declare(strict_types=1);

namespace App\Models\Queries;

final class PaginationQuery implements Query
{ 
    private readonly int $page;
    private readonly int $pageSize;

    public function __construct(
        int $page,
        int $pageSize
    ) {
        $this->page = max($page, 1);
        $this->pageSize = min($pageSize, 50);
    }

    public static function fromInput(array $input): self
    {
        return new self(
            page:    (int)($input['page'] ?? 1),
            pageSize: (int)($input['ps'] ?? 25)
        );
    }

    public function page() :int { return $this->page; }
    public function pageSize() :int { return $this->pageSize; }
}