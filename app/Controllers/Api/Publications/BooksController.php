<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Publications\BooksService;

use App\Models\Queries\Publications\BooksListQuery;
use App\Extensions\Parsers\RouteParamParser;

use App\Controllers\Controller;

use App\Http\HttpContext;
use App\Exceptions\ValidationException;

use App\ErrorCodes;
use App\Constants;

final class BooksController extends Controller
{
    public function __construct(
        private readonly BooksService $booksService
    ){}

    public function list(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $query = BooksListQuery::fromInput($context->request->query, $userId);
        $paginatedList = $this->booksService->getListWithContext($query);
        return $this->jsonList($paginatedList->getArray(), [
                'page' => $paginatedList->getPage(),
                'pageSize' => $paginatedList->getPageSize(),
                'hasNext' => $paginatedList->hasNext(),
            ]);
    }
    
    public function save(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);
    
        $this->booksService->save($userId, $bookId);
        return $this->jsonEmpty(201);
    }

    public function deleteSave(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');

        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $this->booksService->deleteSave($userId, $bookId);
        return $this->jsonEmpty(204);
    }
}