<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Publications\BooksService;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\StatusQuery;

use App\Controllers\Controller;

use App\Http\HttpContext;

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
            return $this->jsonError(new Error(ErrorCodes::UNAUTH_TRY), 401, "Authorize first");

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromInput($context->request->query);
        $sortQ = SortQuery::fromInput($context->request->query);
        $statusQ = StatusQuery::fromInput($context->request->query);
        $searchQ = $context->query('q', '');
        $isbnQ = $context->query('isbn', NULL);

        $genreId = $context->query('genre', 0);
        if (!is_int($genreId) || $genreId == 0)
            $genreId = NULL;

        $creatorId = $context->query('genre', 0);
        if (!is_int($creatorId) || $creatorId == 0)
            $creatorId = NULL;

        try{
            $paginatedList = $this->booksService->getList($pageQ, $searchQ, $propsQ, $sortQ, $statusQ, $userId, $genreId, $creatorId,$isbnQ);
            return $this->jsonList($paginatedList->getArray(), [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                ]);
        }
        catch(ValidationException $e){
            return $this->jsonValidationErrors($e->errors());
        }
    }
}