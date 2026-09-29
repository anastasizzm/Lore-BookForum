<?php
declare(strict_types=1);

namespace App\Controllers\Feed;

use App\Lib\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\PostsService;

use App\Http\HttpContext;
use App\Http\Response;
use App\Models\Posts\Post;

use App\Forms\Queries\PaginationQuery;
use App\Forms\Queries\PropertiesQuery;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;

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

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromInput($context->request->query);
        $propsQ->setType(Post::class);
        $searchQ = $context->query('q', '');

        $parentId = $context->query('parent', 0);
        if (!is_int($parentId) || $parentId == 0) $parentId = NULL;

        $creatorId = $context->query('creator', 0);
        if (!is_int($creatorId) || $creatorId == 0) $creatorId = NULL;

        $publicationId = $context->query('publication', 0);
        if (!is_int($publicationId) || $publicationId == 0) $publicationId = NULL;
        
        $userContext = $usersService->loadContext($userId);
        try{
            $paginatedList = $postsService->getList($pageQ, $searchQ, $propsQ, $parentId, $publicationId, $creatorId);
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
                'innerMessages' => array_map(
                    static fn(string $item, array $fails) => new InnerMessage(InnerMessageType::Error, $item, implode("\n", $fails)), 
                    array_keys($e->errors), 
                    $e->errors), 
                'user' => $userContext
            ]);
        }
    }
}