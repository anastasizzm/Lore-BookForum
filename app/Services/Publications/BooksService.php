<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\BooksRepository;
use App\Services\Enrichers\PublicationContextEnricher;

use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Publications\Book;
use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ReadingStatus;
use App\Models\UserContext\WithContext;

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
        private readonly PublicationContextEnricher $enricher,
        private readonly UnitOfWork $uow
    ){}

    public function getList(BooksListQuery $query) : PaginatedList
    {
        $items = $this->getRawList($query);
        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();
        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function getListWithContext(BooksListQuery $query) : PaginatedList 
    {
        $items = $this->getRawList($query);
        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();
        $enriched = $this->enricher->enrich(
            $items,
            $query->userFilters?->viewerId
        );

        return PaginatedList::fromArray($enriched, $page, $pageSize);
    }

    private function getRawList(BooksListQuery $query)
    {
        $errors = [];
        $isValid = $query->properties->validateForType(Publication::class, $errors);
        if(!$isValid) throw new ValidationException($errors);
        
        $sortEnum = $query->sort->hasData() 
            ? EnumExtensions::tryResolve(PublicationsSortBy::class, $query->sort->sortString()) 
            : PublicationsSortBy::Newest;
        
        $userCriteria = $query->userFilters == NULL || $query->userFilters->isEmpty()
            ? NULL
            : new UserRelationCriteria(
                $query->userFilters->viewerId,
                $query->userFilters->status->hasData() 
                    ? EnumExtensions::tryResolve(ReadingStatus::class, $query->status->status())
                    : ReadingStatus::None,
                $query->userFilters->savedOnly,
            );

        return $this->booksRepo->getList(
            new BooksCriteria(
                $query->pagination->page(),
                $query->pagination->pageSize(),
                $sortEnum,
                $query->filters,
                $userCriteria
            ),
            $query->properties->getProps()
        );
    }

    public function retrieve(int $bookId, PropertiesQuery $props) : ?Book
    {
        $errors = [];
        $isValid = $props->validateForType(Book::class, $errors);
        if(!$isValid) throw new ValidationException($errors);

        return $this->booksRepo->retrieve($bookId, $props->getProps());
    }

    public function retrieveWithContext(int $bookId, PropertiesQuery $props, int $currentUserId) : ?WithContext 
    {
        $item = $this->retrieve($bookId, $props);
        if ($item === NULL) return NULL;

        return $this->enricher->enrichOne(
            $item,
            $currentUserId
        );
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