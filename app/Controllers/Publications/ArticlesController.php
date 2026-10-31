<?php
declare(strict_types=1);

namespace App\Controllers\Publications;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\ArticlesService;

use App\Models\Publications\PublicationShort;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;

use App\Models\Queries\Publications\ArticlesListQuery;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Filters\Publications\ArticlesFilters;
use App\Models\Filters\Publications\UserRelationFilters;
use App\Forms\Publications\ArticleForm;
use App\Extensions\Parsers\RouteParamParser;
use App\Extensions\Parsers\QueryParser;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;

use App\Constants;

final class ArticlesController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly ArticlesService $articlesService,
        private readonly UrlGenerator $url
    ){}

    public function list(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new ArticlesListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator"),
                filters: ArticlesFilters::fromPublic($q),
                userFilters: UserRelationFilters::fromAll($q, $userId),
                viewerId: $userId
            );

            $paginatedList = $this->articlesService->getListWithContext($query);
            return $this->render('library/library-list', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                    'type' => 'article'
                ], 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
        catch(ValidationException $e){
            return $this->render('library/library-list', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }    
    }

    public function adminList(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $q = $context->request->query;
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new ArticlesListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator+owner+genre"),
                filters: ArticlesFilters::fromPublic($q),
                userFilters: UserRelationFilters::fromAll($q, $userId),
                viewerId: $userId
            );
    
            $paginatedList = $this->articlesService->getListWithContext($query);
            return $this->render('admin/articles', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                    'type' => 'article'
                ], 
                'user' => $userContext,
            ]);
        }
        catch(ValidationException $e){
            return $this->render('admin/articles', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
            ]);
        }    
    }

    public function draftList(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new ArticlesListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator"),
                filters: ArticlesFilters::fromDraft($q),
                userFilters: UserRelationFilters::fromAll($q, $userId),
                viewerId: $userId
            );

            $paginatedList = $this->articlesService->getListWithContext($query);
            return $this->render('drafts/drafts', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                    'type' => 'article'
                ], 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
        catch(ValidationException $e){
            return $this->render('drafts/drafts', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }    
    }

    public function savedList(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new ArticlesListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator"),
                filters: ArticlesFilters::fromInput($q),
                userFilters: UserRelationFilters::fromSaved($q, $userId),
                viewerId: $userId
            );

            $paginatedList = $this->articlesService->getListWithContext($query);
            return $this->render('saved/saved-list', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                    'type' => 'article'
                ], 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
        catch(ValidationException $e){
            return $this->render('saved/saved-list', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }   
    }

    public function retrieve(HttpContext $context, string $articleId)
    {
        $articleId = RouteParamParser::positiveInt(['a' => $articleId], 'a');
        
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $propsQ = PropertiesQuery::fromRaw("creator+genre+type");

        $userContext = $this->usersService->loadContext($userId);
        try{
            $item = $this->articlesService->retrieveWithContext($articleId, $propsQ, $userId);
            if ($item === null)
                return $this->renderNotFound();
            
            return $this->render('article/article-details', [
                'wrapper' => $item,
                'user' => $userContext
            ]);
        }
        catch(ValidationException $e){
            return $this->render('article/article-details', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
            ]);
        }
    }

    public function getCreate(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login'));

        $userContext = $this->usersService->loadContext($userId);
        return $this->render('article/article-form', [
            'user' => $userContext
        ]);
    }

    public function create(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url($this->url->url('login', query: ['redirect' => $context->request->path])));

        $formData = $context->request->body();
        try{
            $articleId = $this->articlesService->create(
                ArticleForm::fromInput($formData),
                $userId
            );
            return Response::redirect($this->url->url('articles.retrieve', ['articleId' => $articleId]));
        }
        catch(ValidationException $e){
            $userContext = $this->usersService->loadContext($userId);
            return $this->render('article/article-form', [
                'form' => $formData, 
                'errors' => $e->errors(),
                'user' => $userContext
            ], 422);
        }
    }
}