<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\TypesRepository;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\TypeForm;
use App\Validators\Additionals\TypeFormValidator;
use App\Models\Errors\ErrorBag;
use App\Extensions\EnumExtensions;
use App\Models\PaginatedList;
use App\Models\Enums\BasicModelSortBy;
use App\Exceptions\ConflictException;

final class TypesService
{
    public function __construct(
        private readonly TypesRepository $typesRepo,
        private readonly TypeFormValidator $validator
    ){}

    public function getList(BasicModelListQuery $query) : PaginatedList
    {
        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();

        $sortEnum = ($query->sort->hasData()
            ? EnumExtensions::tryResolve(BasicModelSortBy::class, $query->sort->sortString())
            : null) ?? BasicModelSortBy::Alphabet;

        $items = $this->typesRepo->getList(
            $page, 
            $pageSize, 
            $sortEnum,
            $query->search,
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function create(TypeForm $form, ErrorBag $bag) : ?int
    {
        if (!$this->validator->validate($form, $bag)) {
            return null;
        }

        return $this->typesRepo->create($form->translations);
    }

    public function update(int $id, TypeForm $form, ErrorBag $bag) : bool
    {
        if (!$this->validator->validate($form, $bag)) {
            return false;
        }

        return $this->typesRepo->update($id, $form->translations);
    }

    public function delete(int $id) : bool
    {
        try {
            return $this->typesRepo->delete($id);
        } catch (\PDOException $e) {
            if (($e->getCode() === '23503') || str_contains($e->getMessage(), 'foreign key')) {
                throw new ConflictException();
            }
            throw $e;
        }
    }
}