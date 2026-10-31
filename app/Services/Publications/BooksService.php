<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\BooksRepository;
use App\Repositories\Publications\UsersReadRepository;
use App\Services\Enrichers\PublicationContextEnricher;
use App\Services\Storage\FileService;
use App\Factories\CriteriasFactory;

use App\Forms\Publications\BookForm;
use App\Forms\Publications\BookPatchForm;
use App\Forms\Publications\BookContentForm;
use App\Validators\Publications\BookFormValidator;
use App\Validators\Publications\BookPatchFormValidator;
use App\Validators\Publications\ReadProgressFormValidator;
use App\Validators\Publications\RateFormValidator;

use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Publications\Book;
use App\Models\Publications\BookFiles;
use App\Models\Publications\BookModel;
use App\Models\Publications\BookPatchModel;
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
use App\Exceptions\NotFoundException;
use App\Exceptions\ConflictException;
use App\ErrorCodes;
use App\Lib\I18n\Translator;

use PDO;

final class BooksService extends PublicationsService
{
    public function __construct(
        BooksRepository $repository,
        UsersReadRepository $usersReadRepo,
        BookExceptionTranslator $exceptionTranslator,
        ReadProgressFormValidator $progressValidator,
        RateFormValidator $rateValidator,
        Translator $translator,
        UnitOfWork $uow,
        private readonly PublicationContextEnricher $enricher,
        private readonly BookFormValidator $validator,
        private readonly BookPatchFormValidator $patchValidator,
        private readonly FileService $fileService
    ){
        parent::__construct($repository, $usersReadRepo, $exceptionTranslator, 
            $progressValidator, $rateValidator, $translator, $uow);
    }

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
            $query->viewerId
        );

        return PaginatedList::fromArray($enriched, $page, $pageSize);
    }

    private function getRawList(BooksListQuery $query)
    {
        $errors = [];
        $isValid = $query->properties->validateForType(Publication::class, $errors);
        if(!$isValid) throw new ValidationException($errors);
        
        $criteria = CriteriasFactory::buildBookFromQuery($query, Publication::class);
        return $this->repository->getList(
            $criteria,
            $query->properties->getProps()
        );
    }

    public function retrieve(int $bookId, PropertiesQuery $props) : ?Book
    {
        $errors = [];
        $isValid = $props->validateForType(Book::class, $errors);
        if(!$isValid) throw new ValidationException($errors);

        return $this->repository->retrieve($bookId, $props->getProps());
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

    public function getContents(int $bookId) : array
    {
        return $this->repository->getContents($bookId);
    } 

    public function create(
        BookForm $form, 
        int $creatorId
    ) : int
    {
        $bag = $this->validator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());
        
        try{
            return $this->uow->transactional(function () use ($form, $creatorId) :int {
                $model = BookModel::fromForm($form);
                
                $bookId = $this->repository->create($model, $creatorId);
                $fileIds = $this->repository->addPendingContents($bookId, $form->contentIds);
                $this->fileService->activateMany($fileIds);

                return $bookId;
            });
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function update(
        int $bookId,
        BookPatchForm $form
    ) : void 
    {
        $bag = $this->patchValidator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());

        try{
            $model = BookPatchModel::fromForm($form);
            $updated = $this->repository->update($bookId, $model);
            if (!$updated) throw new NotFoundException($this->translator->t('errors.common.not_found'), ErrorCodes::NOT_FOUND);
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function addContent(int $bookId, BookContentForm $form) : void 
    {
        try{
            $this->repository->addContent($bookId, $form->contentId);
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function removeContent(int $bookId, Uuid $contentId) : void 
    {
        try{
            $removed = $this->repository->removeContent($bookId, $contentId);
            if ($removed) try{$this->fileService->delete($contentId);}catch(ConflictException $e) {}
            else throw new NotFoundException($this->translator->t("errors.common.not_found"), ErrorCodes::NOT_FOUND);
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }
    }
}