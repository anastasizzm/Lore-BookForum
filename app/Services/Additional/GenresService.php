<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\GenresRepository;
use App\Models\Queries\PaginationQuery;

use App\Models\PaginatedList;

final class GenresService
{
    public function __construct(
        private readonly GenresRepository $genresRepo
    ){}

    public function getList(
        PaginationQuery $pageQ,
        string $search,
    ) : PaginatedList
    {
        $page = $pageQ->page();
        $pageSize = $pageQ->pageSize();

        $items = $this->genresRepo->getList(
            $page, 
            $pageSize, 
            $search, 
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }
}