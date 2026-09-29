<?php
declare(strict_types=1);

namespace App\Models;

final class PaginatedList 
{
    public function __construct(
        private readonly array $list,
        private readonly int $page,
        private readonly int $pageSize,
        private readonly bool $hasNextPage
    ){}

    public static function fromArray(array $list, int $page, int $pageSize) : self
    {
        $hasNext = count($list) > $pageSize;
        if ($hasNext) {
            $list = array_slice($list, 0, $pageSize);
        }

        return new self($list, $page, $pageSize, $hasNext);
    }

    public function hasNext() : bool { return $this->hasNextPage; }
    public function getArray() : array { return $this->list; }
    public function getPageSize() : int { return $this->pageSize; }
    public function getPage() : int { return $this->page; }
}