<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\GenresRepository;
use App\Models\Queries\Additional\BasicModelListQuery;

use App\Models\PaginatedList;
use App\Models\Enums\BasicModelSortBy;

final class GenresService
{
    public function __construct(
        private readonly GenresRepository $genresRepo
    ){}

    public function getList(BasicModelListQuery $query) : PaginatedList
    {
        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();

        $sortEnum = $query->sort->hasData() 
            ? EnumExtensions::tryResolve(BasicModelSortBy::class, $query->sort->sortString()) 
            : BasicModelSortBy::Newest;

        $items = $this->genresRepo->getList(
            $page, 
            $pageSize, 
            $sortEnum,
            $query->search,
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }
}