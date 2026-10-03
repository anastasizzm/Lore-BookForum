<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\ArticlesRepository;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\StatusQuery;
use App\Models\Queries\TypeQuery;
use App\Models\Filters\UserByPublicationFilters;
use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Publications\Article;

use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ArticleType;
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
        private readonly UnitOfWork $uow
    ){}

    public function getList(
        PaginationQuery $pageQ,
        string $search,
        PropertiesQuery $props,
        int $currentUserId,
        bool $savedOnly = false,
        ?SortQuery $sort = null,
        ?StatusQuery $status = null,
        ?int $genreId = null,
        ?int $creatorId = null,
        ?int $bookId = null,
        ?string $doi = null,
        ?TypeQuery $type = null
    ) : PaginatedList
    {
        $errors = [];
        $isValid = $props->validateForType(Publication::class, $errors);
        if(!$isValid) throw new ValidationException($errors);
        
        $sortEnum = $sort == null ? null : EnumExtensions::tryResolve(PublicationsSortBy::class, $sort->sortString());
        $sortEnum ??= PublicationsSortBy::Newest;
        
        $statusEnum = $status == null ? null : EnumExtensions::tryResolve(ReadingStatus::class, $status->status());
        $statusEnum ??= ReadingStatus::None;

        $typeEnum = $type == null ? null : EnumExtensions::tryResolve(ArticleType::class, $type->type());

        $userByFilter = new UserByPublicationFilters($currentUserId, $statusEnum, $savedOnly);

        $page = $pageQ->page();
        $pageSize = $pageQ->pageSize();

        $items = $this->articlesRepo->getList(
            $page, 
            $pageSize, 
            $search, 
            $sortEnum,
            $props->getProps(),
            $genreId, 
            $creatorId,
            $bookId,
            $doi,
            $typeEnum,
            $userByFilter
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