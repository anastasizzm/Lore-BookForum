<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\ArticlesRepository;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\TypeQuery;

use App\Models\PaginatedList;
use App\Models\Publications\Publication;
use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ArticleType;

use App\Extensions\EnumExtensions;

final class ArticlesService
{
    public function __construct(
        private readonly ArticlesRepository $articlesRepo
    ){}

    public function getList(
        PaginationQuery $pageQ,
        string $search,
        PropertiesQuery $props,
        ?SortQuery $sort = null,
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

        $typeEnum = $type == null ? null : EnumExtensions::tryResolve(ArticleType::class, $type->type());

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
            $typeEnum
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }
}