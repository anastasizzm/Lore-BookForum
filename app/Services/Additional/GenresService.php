<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\GenresRepository;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\GenreForm;
use App\Validators\Additionals\GenreFormValidator;
use App\Models\Errors\ErrorBag;
use App\Extensions\EnumExtensions;
use App\Models\PaginatedList;
use App\Models\Enums\BasicModelSortBy;
use App\Exceptions\ConflictException;

final class GenresService
{
    public function __construct(
        private readonly GenresRepository $genresRepo,
        private readonly GenreFormValidator $validator
    ){}

    public function getList(BasicModelListQuery $query) : PaginatedList
    {
        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();

        $sortEnum = ($query->sort->hasData()
            ? EnumExtensions::tryResolve(BasicModelSortBy::class, $query->sort->sortString())
            : null) ?? BasicModelSortBy::Alphabet;

        $items = $this->genresRepo->getList(
            $page, 
            $pageSize, 
            $sortEnum,
            $query->search,
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function create(GenreForm $form, ErrorBag $bag) : ?int
    {
        if (!$this->validator->validate($form, $bag)) {
            return null;
        }

        return $this->genresRepo->create($form->translations);
    }

    public function update(int $id, GenreForm $form, ErrorBag $bag) : bool
    {
        if (!$this->validator->validate($form, $bag)) {
            return false;
        }

        return $this->genresRepo->update($id, $form->translations);
    }

    public function delete(int $id) : bool
    {
        try {
            return $this->genresRepo->delete($id);
        } catch (\PDOException $e) {
            if (($e->getCode() === '23503') || str_contains($e->getMessage(), 'foreign key')) {
                throw new ConflictException();
            }
            throw $e;
        }
    }
}