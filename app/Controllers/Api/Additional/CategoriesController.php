<?php
declare(strict_types=1);

namespace App\Controllers\Api\Additional;

use App\Services\Additional\CategoriesService;
use App\Models\Queries\Additional\BasicModelListQuery;

use App\Controllers\Controller;

use App\Http\HttpContext;

use App\ErrorCodes;
use App\Constants;

final class CategoriesController extends Controller
{
    public function __construct(
        private readonly CategoriesService $categoriesService
    ){}

    public function list(HttpContext $context)
    {
        $query = BasicModelListQuery::fromInput($context->request->query);
        $paginatedList = $this->categoriesService->getList($query);
        return $this->jsonList($paginatedList->getArray(), [
                'page' => $paginatedList->getPage(),
                'pageSize' => $paginatedList->getPageSize(),
                'hasNext' => $paginatedList->hasNext(),
            ]);
    }
}