<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\LanguagesRepository;
use App\Models\Queries\PaginationQuery;
use App\Models\PaginatedList;

final class LanguagesService
{
    public function __construct(
        private readonly LanguagesRepository $langRepo
    ){}

    public function getList(PaginationQuery $query) : PaginatedList
    {
        $page = $query->page();
        $pageSize = $query->pageSize();

        $items = $this->langRepo->getList(
            $page, 
            $pageSize, 
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }
}