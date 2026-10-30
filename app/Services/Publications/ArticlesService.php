<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\ArticlesRepository;
use App\Repositories\Publications\UsersReadRepository;
use App\Services\Enrichers\PublicationContextEnricher;
use App\Factories\CriteriasFactory;

use App\Validators\Publications\RateFormValidator;

use App\Forms\Publications\ArticleForm;
use App\Models\Criterias\Publications\ArticlesCriteria;
use App\Models\Criterias\Publications\UserRelationCriteria;
use App\Validators\Publications\ReadProgressFormValidator;
use App\Validators\Publications\ArticleFormValidator;
use App\Models\Queries\Publications\ArticlesListQuery;
use App\Models\Queries\PropertiesQuery;

use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Publications\Article;
use App\Models\Publications\ArticleModel;
use App\Models\Publications\ArticlePatchModel;
use App\Forms\Publications\ArticlePatchForm;
use App\Models\UserContext\WithContext;

use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ReadingStatus;

use App\Extensions\EnumExtensions;
use App\Services\Configuration\UnitOfWork;

use App\Exceptions\Translators\ArticleExceptionTranslator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Lib\I18n\Translator;

use PDO;

final class ArticlesService extends PublicationsService
{
    public function __construct(
        ArticlesRepository $repository,
        UsersReadRepository $usersReadRepo,
        ArticleExceptionTranslator $exceptionTranslator,
        ReadProgressFormValidator $progressValidator,
        RateFormValidator $rateValidator,
        Translator $translator,
        UnitOfWork $uow,
        private readonly ArticleFormValidator $validator,
        private readonly PublicationContextEnricher $enricher,
    ){
        parent::__construct($repository, $usersReadRepo, $exceptionTranslator, $progressValidator, $rateValidator, $translator, $uow);
    }

    public function getList(ArticlesListQuery $query) : PaginatedList
    {
        $items = $this->getRawList($query);
        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();
        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function getListWithContext(ArticlesListQuery $query) : PaginatedList 
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

    private function getRawList(ArticlesListQuery $query) : array
    {
        $errors = [];
        $isValid = $query->properties->validateForType(Publication::class, $errors);
        if(!$isValid) throw new ValidationException($errors);
        
        $criteria = CriteriasFactory::buildArticleFromQuery($query, Publication::class);
        return $this->repository->getList(
            $criteria,
            $query->properties->getProps()
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function retrieve(int $articleId, PropertiesQuery $props) : ?Article
    {
        $errors = [];
        $isValid = $props->validateForType(Article::class, $errors);
        if(!$isValid) throw new ValidationException($errors);

        return $this->repository->retrieve($articleId, $props->getProps());
    }

    public function retrieveWithContext(int $articleId, PropertiesQuery $props, int $currentUserId) : ?WithContext 
    {
        $item = $this->retrieve($articleId, $props);
        if ($item === NULL) return NULL;

        return $this->enricher->enrichOne(
            $item,
            $currentUserId
        );
    }

    public function create(
        ArticleForm $form, 
        int $creatorId
    ) : int
    {
        $bag = $this->validator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());
        
        try{
            $model = ArticleModel::fromForm($form);
            return $this->repository->create($model, $creatorId);
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function update(
        int $articleId,
        ArticlePatchForm $form
    ) : void 
    {
        $bag = $this->patchValidator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());

        try{
            $model = ArticlePatchModel::fromForm($form);
            $updated = $this->repository->update($articleId, $model);
            if (!$updated) throw new NotFoundException($this->translator->t('errors.common.not_found'), ErrorCodes::NOT_FOUND);
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }
    }
}