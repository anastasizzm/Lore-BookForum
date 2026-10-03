<?php
declare(strict_types=1);

namespace App\Controllers\Publications;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\BooksService;

use App\Models\Publications\PublicationShort;

use App\Http\HttpContext;
use App\Http\Response;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;

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

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromRaw("creator");
        $searchQ = $context->query('q', '');
        $filterState = $context->query('f', 'closed');
        
        $userContext = $this->usersService->loadContext($userId);
        try{
            $paginatedList = $this->booksService->getList($pageQ, $searchQ, $propsQ, $userId);
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

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromRaw("creator");
        $searchQ = $context->query('q', '');
        $filterState = $context->query('f', 'closed');
        
        $userContext = $this->usersService->loadContext($userId);
        try{
            $paginatedList = $this->booksService->getList($pageQ, $searchQ, $propsQ, $userId, true);
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
        $bookId = (int)$bookId;

        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect('login');

        $propsQ = PropertiesQuery::fromRaw("creator+genre+category");

        $userContext = $this->usersService->loadContext($userId);
        try{
            $item = $this->booksService->retrieve($bookId, $propsQ);
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
                'filterState' => $filterState
            ]);
        }
    }
}