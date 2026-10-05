<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\ArticlesRepository;
use App\Services\Enrichers\PublicationContextEnricher;

use App\Models\Criterias\Publications\ArticlesCriteria;
use App\Models\Criterias\Publications\UserRelationCriteria;
use App\Models\Queries\Publications\ArticlesListQuery;
use App\Models\Queries\PropertiesQuery;

use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Publications\Article;
use App\Models\UserContext\WithContext;

use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ReadingStatus;

use App\Extensions\EnumExtensions;
use App\Services\Configuration\UnitOfWork;

use App\Exceptions\Translators\ArticleExceptionTranslator;
use App\Exceptions\ValidationException;

use PDO;

final class ArticlesService
{
    public function __construct(
        private readonly ArticlesRepository $articlesRepo,
        private readonly ArticleExceptionTranslator $translator,
        private readonly PublicationContextEnricher $enricher,
        private readonly UnitOfWork $uow
    ){}

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
            $query->userFilters?->viewerId
        );

        return PaginatedList::fromArray($enriched, $page, $pageSize);
    }

    private function getRawList(ArticlesListQuery $query) : array
    {
        $errors = [];
        $isValid = $query->properties->validateForType(Publication::class, $errors);
        if(!$isValid) throw new ValidationException($errors);
        
        $sortEnum = $query->sort->hasData()
            ? (EnumExtensions::tryResolve(PublicationsSortBy::class, $query->sort->sortString())
                ?? PublicationsSortBy::Newest)
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
        
        return $this->articlesRepo->getList(
            new ArticlesCriteria(
                $query->pagination->page(),
                $query->pagination->pageSize(),
                $sortEnum,
                $query->filters,
                $userCriteria
            ),
            $query->properties->getProps()
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function retrieve(int $articleId, PropertiesQuery $props) : ?Article
    {
        $errors = [];
        $isValid = $props->validateForType(Article::class, $errors);
        if(!$isValid) throw new ValidationException($errors);

        return $this->articlesRepo->retrieve($articleId, $props->getProps());
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

    public function save(int $userId, int $articleId) : void
    {
        try{
            $this->uow->transactional(function (PDO $pdo) use($userId, $articleId) {
                if (!$this->articlesRepo->checkType($articleId))
                    throw new ValidationException(['articleId' => ['Article not found']]);

                $this->articlesRepo->save($userId, $articleId); 
            });
        }
        catch(\PDOException $e){
            throw $this->translator->translate($e);
        }
    }

    public function deleteSave(int $userId, int $articleId) : void
    {
        try{
            $this->uow->transactional(function (PDO $pdo) use($userId, $articleId) {
                if (!$this->articlesRepo->checkType($articleId))
                    throw new ValidationException(['articleId' => ['Article not found']]);

                $this->articlesRepo->deleteSave($userId, $articleId);
            });
        }
        catch(\PDOException $e){
            throw $this->translator->translate($e);
        }
    }
}