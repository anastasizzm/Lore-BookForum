<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\CategoriesRepository;
use App\Models\Queries\PaginationQuery;

use App\Models\PaginatedList;

final class CategoriesService
{
    public function __construct(
        private readonly CategoriesRepository $categoriesRepo
    ){}

    public function getList(
        PaginationQuery $pageQ,
        string $search,
    ) : PaginatedList
    {
        $page = $pageQ->page();
        $pageSize = $pageQ->pageSize();

        $items = $this->categoriesRepo->getList(
            $page, 
            $pageSize, 
            $search, 
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }
}