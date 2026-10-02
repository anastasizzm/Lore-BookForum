<?php
declare(strict_types=1);

namespace App\Controllers\Publications;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\ArticlesService;

use App\Models\Publications\PublicationShort;

use App\Http\HttpContext;
use App\Http\Response;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;

use App\Constants;

final class ArticlesController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly ArticlesService $articlesService
    ){}

    public function list (HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect('login');

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromRaw("creator");
        $searchQ = $context->query('q', '');
        $filterState = $context->query('f', 'closed');
        
        $userContext = $this->usersService->loadContext($userId);
        try{
            $paginatedList = $this->articlesService->getList($pageQ, $searchQ, $propsQ, $userId);
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
                'innerMessages' => $e->toMessage(), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
    }

    public function savedList(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect('login');

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromRaw("creator");
        $searchQ = $context->query('q', '');
        $filterState = $context->query('f', 'closed');
        
        $userContext = $this->usersService->loadContext($userId);
        try{
            $paginatedList = $this->articlesService->getList($pageQ, $searchQ, $propsQ, $userId, true);
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
}