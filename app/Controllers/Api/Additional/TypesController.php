<?php
declare(strict_types=1);

namespace App\Controllers\Api\Additional;

use App\Services\Additional\TypesService;
use App\Models\Queries\Additional\BasicModelListQuery;

use App\Controllers\Controller;

use App\Http\HttpContext;

use App\ErrorCodes;
use App\Constants;

final class TypesController extends Controller
{
    public function __construct(
        private readonly TypesService $typesService
    ){}

    public function list(HttpContext $context)
    {
        $query = BasicModelListQuery::fromInput($context->request->query);
        $paginatedList = $this->typesService->getList($query);
        return $this->jsonList($paginatedList->getArray(), [
                'page' => $paginatedList->getPage(),
                'pageSize' => $paginatedList->getPageSize(),
                'hasNext' => $paginatedList->hasNext(),
            ]);
    }
}