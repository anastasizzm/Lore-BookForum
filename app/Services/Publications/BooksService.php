<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\BooksRepository;
use App\Repositories\Publications\UsersReadRepository;
use App\Services\Enrichers\PublicationContextEnricher;
use App\Services\Storage\FileService;

use App\Forms\Publications\BookForm;
use App\Forms\Publications\RateForm;
use App\Forms\Publications\BookProgressForm;
use App\Validators\Publications\BookFormValidator;
use App\Validators\Publications\BookProgressFormValidator;
use App\Validators\Publications\RateFormValidator;

use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Publications\Book;
use App\Models\Publications\PublicationShort;
use App\Models\Publications\BookFiles;
use App\Models\Publications\BookModel;
use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\BookFormat;
use App\Models\Enums\ReadingStatus;
use App\Models\Enums\FileCategory;
use App\Models\Uuid;
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
        private readonly UsersReadRepository $usersReadRepo,
        private readonly BookExceptionTranslator $translator,
        private readonly PublicationContextEnricher $enricher,
        private readonly UnitOfWork $uow,
        private readonly BookFormValidator $validator,
        private readonly BookProgressFormValidator $progressValidator,
        private readonly RateFormValidator $rateValidator,
        private readonly FileService $fileService
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
                    ? EnumExtensions::tryResolve(ReadingStatus::class, $query->userFilters->status->status())
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

    public function retrieveShort(int $bookId) : ?PublicationShort 
    {
        return $this->booksRepo->retrieveShort($bookId);
    }

    public function identifyReadContent(int $bookId, BookFormat $format = BookFormat::Pdf): ?Uuid
    {
        return $this->booksRepo->identifyContent($bookId, $format->value);
    }

    public function getProgress(int $userId, int $bookId, BookFormat $format): ?UserRead
    {
        return $this->usersReadRepo->find($userId, $bookId, $format);
    }

    public function saveProgress(
    int        $userId,
    int        $bookId,
    BookProgressForm $form
    ): void {
        $bag = $this->progressValidator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());

        $this->uow->transactional(function (PDO $pdo) use (
            $userId, $bookId, $form
        ): void {
            $this->usersReadRepo->upsert(
                userId:        $userId,
                publicationId: $bookId,
                format:        $form->format,
                position:      $form->position,
                isFinished:    $form->isFinished,
            );
        });
    }

    public function finishReading(
        int        $userId,
        int        $bookId,
        BookFormat $format,
    ): void {
        $this->uow->transactional(function (PDO $pdo) use (
            $userId, $bookId, $format
        ): void {
            $this->usersReadRepo->markFinished(
                userId:        $userId,
                publicationId: $bookId,
                format:        $format,
            );
        });
    }

    public function resetProgress(
        int         $userId,
        int         $bookId,
        ?BookFormat $format,
    ): void {
        $this->uow->transactional(function (PDO $pdo) use (
            $userId, $bookId, $format
        ): void {
            if ($format === null) {
                $this->usersReadRepo->delete($userId, $bookId);
            } else {
                $this->usersReadRepo->deleteForFormat(
                    $userId,
                    $bookId,
                    $format,
                );
            }
        });
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

    public function rate(int $userId, int $bookId, RateForm $form) : void 
    {
        $bag = $this->rateValidator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());

        try{
            $this->uow->transactional(function (PDO $pdo) use($userId, $bookId, $form) {
                if (!$this->booksRepo->checkType($bookId))
                    throw new ValidationException(['bookId' => ['Book not found']]);
                $this->booksRepo->rate($userId, $bookId, $form->rating);
            });
        }
        catch(\PDOException $e)
        {
            throw $this->translator->translate($e);
        }
    }

    public function uprate(int $userId, int $bookId) : void 
    {
        try{
            $this->uow->transactional(function (PDO $pdo) use($userId, $bookId) {
                if (!$this->booksRepo->checkType($bookId))
                    throw new ValidationException(['bookId' => ['Book not found']]);
                $this->booksRepo->unrate($userId, $bookId);
            });
        }
        catch(\PDOException $e)
        {
            throw $this->translator->translate($e);
        }
    }

    public function create(
        BookForm $form, 
        int $creatorId
    ) : int
    {
        $bag = $this->validator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());
        
        try{
            return $this->uow->transactional(function (PDO $pdo) use ($form, $creatorId) :int {
                $model = BookModel::fromForm($form);
                
                $bookId = $this->booksRepo->create($model, $creatorId);
                $this->booksRepo->addPendingContents($bookId, $form->contentIds);

                return $bookId;
            });
        }
        catch(\PDOException $e){
            throw $this->translator->translate($e);
        }
    }
}