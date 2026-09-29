<?php
declare(strict_types=1);

namespace App\Controllers\Books;

use App\Lib\Controller;

use App\Services\Users\UsersService;
use App\Services\Publications\PublicationsService;

use App\Models\Publications\PublicationShort;

use App\Http\HttpContext;
use App\Http\Response;

use App\Queries\PaginationQuery;
use App\Queries\PropertiesQuery;
use App\Queries\SortQuery;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;

use App\Constants;

final class BooksController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly PublicationsService $pubsService
    ){}

    public function list (HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect('login');

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromRaw("creator");
        $sortQ = SortQuery::fromInput($context->request->query);
        $searchQ = $context->query('q', '');

        $genreId = $context->query('genre', 0);
        if (!is_int($genreId) || $genreId == 0) $genreId = NULL;

        $creatorId = $context->query('creator', 0);
        if (!is_int($creatorId) || $creatorId == 0) $creatorId = NULL;
        
        $userContext = $this->usersService->loadContext($userId);
        try{
            $paginatedList = $this->pubsService->getList($pageQ, $searchQ, $propsQ, $sortQ, $genreId, $creatorId);
            return $this->render('library/library-list', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                    'filters' => [
                        'creator' => $creatorId,
                        'genre' => $genreId,
                        'sort' => $sortQ->sortString()
                    ]
                ], 
                'user' => $userContext
            ]);
        }
        catch(ValidationException $e){
            return $this->render('library/library-list', [
                'innerMessages' => array_map(
                    static fn(string $item, array $fails) => new InnerMessage(InnerMessageType::Error, $item, implode("\n", $fails)), 
                    array_keys($e->errors), 
                    $e->errors), 
                'user' => $userContext
            ]);
        }
    }
}