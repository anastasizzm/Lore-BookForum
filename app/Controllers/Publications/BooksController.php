<?php
declare(strict_types=1);

namespace App\Controllers\Publications;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\BooksService;

use App\Models\Publications\PublicationShort;

use App\Http\HttpContext;
use App\Http\Response;

use App\Models\Queries\Publications\BooksListQuery;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Filters\Publications\BooksFilters;
use App\Models\Filters\Publications\UserRelationFilters;
use App\Extensions\Parsers\RouteParamParser;
use App\Extensions\Parsers\QueryParser;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;

use App\Constants;

final class BooksController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly BooksService $booksService
    ){}

    public function list(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect('login');

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new BooksListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator"),
                filters: BooksFilters::fromInput($q),
                userFilters: UserRelationFilters::fromAll($q, $userId)
            );

            $paginatedList = $this->booksService->getListWithContext($query);
            return $this->render('library/library-list', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                    'type' => 'book'
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

    public function savedList(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect('login');

        $q = $context->request->query;
        $filterState = QueryParser::optionalString($q, 'f') ?? 'closed';
        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = new BooksListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator"),
                filters: BooksFilters::fromInput($q),
                userFilters: UserRelationFilters::fromSaved($q, $userId)
            );

            $paginatedList = $this->booksService->getListWithContext($query);
            return $this->render('saved/saved-list', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                    'type' => 'book'
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

    public function retrieve(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect('login');

        $propsQ = PropertiesQuery::fromRaw("creator+genre+category");

        $userContext = $this->usersService->loadContext($userId);
        try{
            $item = $this->booksService->retrieveWithContext($bookId, $propsQ, $userId);
            if ($item === null)
                return $this->renderNotFound();
            
            return $this->render('book/book-details', [
                'book' => $item,
                'user' => $userContext
            ]);
        }
        catch(ValidationException $e){
            return $this->render('book/book-details', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
            ]);
        }
    }
}