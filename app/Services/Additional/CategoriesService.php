<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\CategoriesRepository;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\CategoryForm;
use App\Validators\Additionals\CategoryFormValidator;
use App\Models\Errors\ErrorBag;
use App\Extensions\EnumExtensions;
use App\Models\PaginatedList;
use App\Models\Enums\BasicModelSortBy;
use App\Exceptions\ConflictException;

final class CategoriesService
{
    public function __construct(
        private readonly CategoriesRepository $categoriesRepo,
        private readonly CategoryFormValidator $validator
    ){}

    public function getList(BasicModelListQuery $query) : PaginatedList
    {
        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();

        $sortEnum = ($query->sort->hasData()
            ? EnumExtensions::tryResolve(BasicModelSortBy::class, $query->sort->sortString())
            : null) ?? BasicModelSortBy::Alphabet;

        $items = $this->categoriesRepo->getList(
            $page, 
            $pageSize, 
            $sortEnum,
            $query->search,
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function create(CategoryForm $form, ErrorBag $bag) : ?int
    {
        if (!$this->validator->validate($form, $bag)) {
            return null;
        }

        return $this->categoriesRepo->create($form->translations);
    }

    public function update(int $id, CategoryForm $form, ErrorBag $bag) : bool
    {
        if (!$this->validator->validate($form, $bag)) {
            return false;
        }

        return $this->categoriesRepo->update($id, $form->translations);
    }

    public function delete(int $id) : bool
    {
        try {
            return $this->categoriesRepo->delete($id);
        } catch (\PDOException $e) {
            if (($e->getCode() === '23503') || str_contains($e->getMessage(), 'foreign key')) {
                throw new ConflictException();
            }
            throw $e;
        }
    }
}