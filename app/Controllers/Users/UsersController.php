<?php
declare(strict_types=1);

namespace App\Controllers\Users;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\BooksService;
use App\Services\Publications\ArticlesService;

use App\Http\HttpContext;
use App\Http\Response;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;

use App\Constants;

final class UsersController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly BooksService $booksService,
        private readonly ArticlesService $articlesService
    ){}

    public function retrieve(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect('login');

        $userContext = $this->usersService->loadContext($currentUserId);
        $userData = $this->usersService->retrieve($userId);
        if ($userData === null)
            return $this->render('message', ['message' => 'The profile is not found', 'statusCode' => 404]);

        return $this->render('profile/profile', ['user' => $userContext, 'userData' => $userData]);
    }

    public function getEdit(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect('login');

        $userContext = $this->usersService->loadContext($currentUserId);
        $userData = $this->usersService->retrieve($userId);
        if ($userData === null)
            return $this->render('message', ['message' => 'The profile is not found', 'statusCode' => 404]);

        return $this->render('profile/profile-edit', ['user' => $userContext, 'userData' => $userData]);
    }

    public function getBooks(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect('login');

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromRaw("creator");
        $searchQ = $context->query('q', '');
        $filterState = $context->query('f', 'closed');

        $userContext = $this->usersService->loadContext($currentUserId);
        try{
            $paginatedList = $this->booksService->getList($pageQ, $searchQ, $propsQ, $currentUserId, creatorId: $userId);
            return $this->render('profile/profile', [
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
                'innerMessages' => array_map(
                    static fn(string $item, array $fails) => new InnerMessage(InnerMessageType::Error, $item, implode("\n", $fails)), 
                    array_keys($e->errors), 
                    $e->errors), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
    }

    public function getArticles(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect('login');

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromRaw("creator");
        $searchQ = $context->query('q', '');
        $filterState = $context->query('f', 'closed');

        $userContext = $this->usersService->loadContext($currentUserId);
        try{
            $paginatedList = $this->articlesService->getList($pageQ, $searchQ, $propsQ, $currentUserId, creatorId: $userId);
            return $this->render('profile/profile', [
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
                'innerMessages' => array_map(
                    static fn(string $item, array $fails) => new InnerMessage(InnerMessageType::Error, $item, implode("\n", $fails)), 
                    array_keys($e->errors), 
                    $e->errors), 
                'user' => $userContext,
                'filterState' => $filterState
            ]);
        }
    }

}