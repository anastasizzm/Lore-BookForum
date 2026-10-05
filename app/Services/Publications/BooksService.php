<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\BooksRepository;

use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Publications\Book;
use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ReadingStatus;

use App\Models\Criterias\Publications\BooksCriteria;
use App\Models\Criterias\Publications\UserRelationCriteria;
use App\Models\Queries\Publications\BooksListQuery;
use App\Models\Queries\PropertiesQuery;

use App\Extensions\EnumExtensions;

use App\Services\Configuration\UnitOfWork;
use App\Exceptions\Translators\BookExceptionTranslator;
use App\Exceptions\ValidationException;


use PDO;

final class BooksService
{
    public function __construct(
        private readonly BooksRepository $booksRepo,
        private readonly BookExceptionTranslator $translator,
        private readonly UnitOfWork $uow
    ){}

    public function getList(BooksListQuery $query) : PaginatedList
    {
        $errors = [];
        $isValid = $query->properties->validateForType(Publication::class, $errors);
        if(!$isValid) throw new ValidationException($errors);
        
        $sortEnum = $query->sort->hasData() 
            ? EnumExtensions::tryResolve(PublicationsSortBy::class, $query->sort->sortString()) 
            : PublicationsSortBy::Newest;
        
        $userCriteria = $query->userFilters == NULL
            ? NULL
            : new UserRelationCriteria(
                $query->userFilters->viewerId,
                $query->userFilters->status->hasData() 
                    ? EnumExtensions::tryResolve(ReadingStatus::class, $query->status->status())
                    : ReadingStatus::None,
                $query->userFilters->savedOnly,
            );

        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();

        $items = $this->booksRepo->getList(
            new BooksCriteria(
                $page,
                $pageSize,
                $sortEnum,
                $query->filters,
                $userCriteria
            ),
            $query->properties->getProps()
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function retrieve(int $bookId, PropertiesQuery $props) : ?Book
    {
        $errors = [];
        $isValid = $props->validateForType(Book::class, $errors);
        if(!$isValid) throw new ValidationException($errors);

        return $this->booksRepo->retrieve($bookId, $props->getProps());
    }

    public function save(int $userId, int $bookId) : void
    {
        try{
            $this->uow->transactional(function (PDO $pdo) use($userId, $bookId) {
                if (!$this->booksRepo->checkType($bookId))
                    throw new ValidationException(['bookId' => ['Book not found']]);
                $this->booksRepo->save($userId, $bookId);
            });
        }
        catch(\PDOException $e)
        {
            throw $this->translator->translate($e);
        }
    }

    public function deleteSave(int $userId, int $bookId) : void
    {
        try{
            $this->uow->transactional(function (PDO $pdo) use($userId, $bookId) {
                if (!$this->booksRepo->checkType($bookId))
                    throw new ValidationException(['bookId' => ['Book not found']]);
                $this->booksRepo->deleteSave($userId, $bookId);
            });
        }
        catch(\PDOException $e)
        {
            throw $this->translator->translate($e);
        }
    }
}