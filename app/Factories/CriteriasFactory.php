<?php
declare(strict_types=1);

namespace App\Factories;

use App\Models\Criterias\Publications\ArticlesCriteria;
use App\Models\Queries\Publications\ArticlesListQuery;
use App\Models\Criterias\Publications\BooksCriteria;
use App\Models\Queries\Publications\BooksListQuery;
use App\Models\Queries\Users\UsersListQuery;
use App\Models\Criterias\Users\UsersCriteria;

use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ReadingStatus;
use App\Models\Enums\UsersSortBy;

use App\Extensions\EnumExtensions;
use App\Models\Criterias\Publications\UserRelationCriteria;

final class CriteriasFactory 
{
    public static function buildUserFromQuery(UsersListQuery $query, string $returnClassType) : UsersCriteria 
    {
        $sortEnum = $query->sort->hasData() 
            ? EnumExtensions::tryResolve(UsersSortBy::class, $query->sort->sortString()) 
            : UsersSortBy::Name;

        return new UsersCriteria(
            $query->pagination->page(),
            $query->pagination->pageSize(),
            $returnClassType,
            $sortEnum,
            $query->search
        );
    }

    public static function buildArticleFromQuery(ArticlesListQuery $query, string $returnClassType) : ArticlesCriteria
    {
        $sortEnum = $query->sort->hasData() 
            ? EnumExtensions::tryResolve(PublicationsSortBy::class, $query->sort->sortString()) 
            : PublicationsSortBy::Newest;
        
        $userCriteria = new UserRelationCriteria(
                $query->userFilters->viewerId,
                $query->userFilters->status->hasData() 
                    ? EnumExtensions::tryResolve(ReadingStatus::class, $query->userFilters->status->status()) ?? ReadingStatus::None
                    : ReadingStatus::None,
                $query->userFilters->savedOnly,
            );

        return new ArticlesCriteria(
            $query->pagination->page(),
            $query->pagination->pageSize(),
            $returnClassType,
            $sortEnum,
            $query->filters,
            $userCriteria,
            $query->viewerId
        );
    }

    public static function buildBookFromQuery(BooksListQuery $query, string $returnClassType) : BooksCriteria
    {
        $sortEnum = $query->sort->hasData() 
            ? EnumExtensions::tryResolve(PublicationsSortBy::class, $query->sort->sortString()) 
            : PublicationsSortBy::Newest;
        
        $userCriteria = new UserRelationCriteria(
                $query->userFilters->viewerId,
                $query->userFilters->status->hasData() 
                    ? EnumExtensions::tryResolve(ReadingStatus::class, $query->userFilters->status->status()) ?? ReadingStatus::None
                    : ReadingStatus::None,
                $query->userFilters->savedOnly,
            );

        return new BooksCriteria(
            $query->pagination->page(),
            $query->pagination->pageSize(),
            $returnClassType,
            $sortEnum,
            $query->filters,
            $userCriteria,
            $query->viewerId
        );
    }
}