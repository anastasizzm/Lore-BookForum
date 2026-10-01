<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\BooksRepository;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\StatusQuery;

use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Filters\ReadingStatusFilters;
use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ReadingStatus;

use App\Extensions\EnumExtensions;

use App\Exceptions\Translators\BookExceptionTranslator;

final class BooksService
{
    public function __construct(
        private readonly BooksRepository $booksRepo,
        private readonly BookExceptionTranslator $translator
    ){}

    public function getList(
        PaginationQuery $pageQ,
        string $search,
        PropertiesQuery $props,
        ?SortQuery $sort = null,
        ?StatusQuery $status = null,
        ?int $currentUserId = null,
        ?int $genreId = null,
        ?int $creatorId = null,
        ?string $isbn = null
    ) : PaginatedList
    {
        $errors = [];
        $isValid = $props->validateForType(Publication::class, $errors);
        if(!$isValid) throw new ValidationException($errors);
        
        $sortEnum = $sort == null ? null : EnumExtensions::tryResolve(PublicationsSortBy::class, $sort->sortString());
        $sortEnum ??= PublicationsSortBy::Newest;
        
        $statusEnum = $status == null ? null : EnumExtensions::tryResolve(ReadingStatus::class, $status->status());
        $statusEnum ??= ReadingStatus::None;

        $statusFilter = is_int($currentUserId) ? new ReadingStatusFilters($currentUserId, $statusEnum) : null;

        $page = $pageQ->page();
        $pageSize = $pageQ->pageSize();

        $items = $this->booksRepo->getList(
            $page, 
            $pageSize, 
            $search, 
            $sortEnum,
            $props->getProps(),
            $genreId, 
            $creatorId,
            $isbn,
            $statusFilter
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function save(int $userId, int $bookId) : void
    {
        try{
            $this->articlesRepo->save($userId, $bookId);
        }
        catch(\PDOException $e)
        {
            throw $this->translator->translate($e);
        }
    }

    public function deleteSave(int $userId, int $bookId) : void
    {
        try{
            $this->articlesRepo->deleteSave($userId, $bookId);
        }
        catch(\PDOException $e)
        {
            throw $this->translator->translate($e);
        }
    }
}