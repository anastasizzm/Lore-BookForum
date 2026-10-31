<?php
declare(strict_types=1);

namespace App\Controllers\Additional;

use App\Controllers\Controller;

use App\Services\Users\UsersService;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Models\Queries\Permissions\RolesUserListQuery;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Extensions\Parsers\QueryParser;
use App\Models\Queries\PropertiesQuery;

use App\Services\Additional\CategoriesService;
use App\Services\Additional\GenresService;
use App\Services\Additional\TypesService;
use App\Services\Permissions\SystemPermissionsService;

use App\Constants;

final class AdminPanelController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly CategoriesService $categoriesService,
        private readonly GenresService $genresService,
        private readonly TypesService $typesService,
        private readonly SystemPermissionsService $permissionsService,
        private readonly UrlGenerator $url
    ){}

    public function dashboard(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $userContext = $this->usersService->loadContext($userId);
        return $this->render('admin/dashboard', ['user' => $userContext]);
    }

    public function categories(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = BasicModelListQuery::fromInput($context->request->query);
            $paginatedList = $this->categoriesService->getList($query);
            return $this->render('admin/categories', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                ], 
                'user' => $userContext,
            ]);
        }
        catch(ValidationException $e){
            return $this->render('admin/categories', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
            ]);
        }    
    }

    public function genres(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = BasicModelListQuery::fromInput($context->request->query);
            $paginatedList = $this->genresService->getList($query);
            return $this->render('admin/genres', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                ], 
                'user' => $userContext,
            ]);
        }
        catch(ValidationException $e){
            return $this->render('admin/genres', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
            ]);
        }    
    }

    public function types(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $userContext = $this->usersService->loadContext($userId);
        try{
            $query = BasicModelListQuery::fromInput($context->request->query);
            $paginatedList = $this->typesService->getList($query);
            return $this->render('admin/types', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                ], 
                'user' => $userContext,
            ]);
        }
        catch(ValidationException $e){
            return $this->render('admin/types', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext,
            ]);
        }    
    }

    public function getSystemRoles(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $userContext = $this->usersService->loadContext($userId);
        $items = $this->permissionsService->getRolesWithContext($userId);
        return $this->render('admin/roles', [
                'items' => $items,
                'user' => $userContext,
            ]);
    }

    public function getUsers(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect($this->url->url('login', query: ['redirect' => $context->request->path]));

        $userContext = $this->usersService->loadContext($userId);
        $q = $context->request->query;
        $query = new RolesUserListQuery(
            pagination: PaginationQuery::fromInput($q),
            sort: SortQuery::fromInput($q),
            properties: PropertiesQuery::fromRaw("roles"),
            roleId: QueryParser::optionalPositiveInt($q, 'role')
        );
        $paginatedList = $this->permissionsService->getUsersWithContext($query, $userId);
        return $this->render('admin/users', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                ], 
                'user' => $userContext,
            ]);
    }
}