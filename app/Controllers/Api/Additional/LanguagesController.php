<?php
declare(strict_types=1);

namespace App\Controllers\Api\Additional;

use App\Services\Additional\LanguagesService;
use App\Models\Queries\PaginationQuery;
use App\Models\Additional\Language;

use App\Controllers\Controller;

use App\Http\HttpContext;
use App\Lib\Settings;

use App\ErrorCodes;
use App\Constants;

final class LanguagesController extends Controller
{
    public function __construct(
        private readonly LanguagesService $languagesService,
        private readonly Settings $settings
    ){}

    public function list(HttpContext $context)
    {
        $query = PaginationQuery::fromInput($context->request->query);
        $paginatedList = $this->languagesService->getList($query);
        return $this->jsonList($paginatedList->getArray(), [
                'page' => $paginatedList->getPage(),
                'pageSize' => $paginatedList->getPageSize(),
                'hasNext' => $paginatedList->hasNext(),
            ]);
    }

    public function fallback(HttpContext $context)
    {
        $default = $this->settings->defaultLocale;
        $model = new Language($default, true);
        return $this->jsonObject($model);
    }
}