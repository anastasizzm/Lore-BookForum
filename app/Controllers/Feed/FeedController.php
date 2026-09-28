<?php
declare(strict_types=1);

namespace App\Controllers\Feed;

use App\Lib\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\PostsService;

use App\Http\HttpContext;
use App\Http\Response;

use App\Forms\Queries\PaginationQuery;

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
        $pageQ = PaginationQuery::fromArray($context->request->query);
        $q = $context->query('q', '');

        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect('login');
        
        $userContext = $usersService->loadContext($userId);

        try{
            $results = $postsService->topList($pageQ, $q);
            return $this->render('feed/feed-list', ['items' => $results['items'], 'meta' => $results['meta'], 'user' => $userContext]);
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