<?php
declare(strict_types=1);

namespace App\Controllers\Api\Additional;

use App\Services\Additional\GenresService;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\StatusQuery;

use App\Controllers\Controller;

use App\Http\HttpContext;

use App\ErrorCodes;

use App\Constants;

final class GenresController extends Controller
{
    public function __construct(
        private readonly GenresService $genresService
    ){}

    public function list(HttpContext $context)
    {
        $pageQ = PaginationQuery::fromInput($context->request->query);
        $searchQ = $context->query('q', '');

        try{
            $paginatedList = $this->genresService->getList($pageQ, $searchQ);
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