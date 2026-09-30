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

    public function list (HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return Response::redirect('login');

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromRaw("creator");
        $searchQ = $context->query('q', '');
        
        $userContext = $this->usersService->loadContext($userId);
        try{
            $paginatedList = $this->booksService->getList($pageQ, $searchQ, $propsQ);
            return $this->render('library/library-list', [
                'items' => $paginatedList->getArray(), 
                'meta' => [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
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