<?php
declare(strict_types=1);

namespace App\Controllers\Feed;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\PostsService;

use App\Http\HttpContext;
use App\Http\Response;

use App\Models\Queries\Publications\PostsListQuery;
use App\Models\Filters\Publications\PostsFilters;
use App\Models\Queries\PaginationQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\PropertiesQuery;

use App\Exceptions\ValidationException;

use App\Constants;

final class FeedController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly PostsService $postsService
    ){}

    public function list(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect('login');

        $userContext = $this->usersService->loadContext($userId);
        $q = $context->request->query;
        try{
            $query = new PostsListQuery(
                pagination: PaginationQuery::fromInput($q),
                sort: SortQuery::fromInput($q),
                properties: PropertiesQuery::fromRaw("creator+publication"),
                filters: PostsFilters::fromInput($q),
            );
            $paginatedList = $this->postsService->getListWithContext($query, $userId);
            return $this->render('feed/feed-list', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext()
                ], 
                'user' => $userContext
            ]);
        }
        catch(ValidationException $e){
            return $this->render('feed/feed-list', [
                'innerMessages' => $e->toMessages(), 
                'user' => $userContext
            ]);
        }
    }
}