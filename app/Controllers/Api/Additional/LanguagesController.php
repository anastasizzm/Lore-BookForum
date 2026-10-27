<?php
declare(strict_types=1);

namespace App\Controllers\Api\Additional;

use App\Services\Additional\LanguagesService;
use App\Models\Queries\PaginationQuery;
use App\Models\Additional\Language;
use App\Forms\Additionals\LanguageForm;
use App\Extensions\Parsers\RouteParamParser;

use App\Controllers\Controller;

use App\Http\HttpContext;
use App\Lib\Settings\Settings;

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

    public function retrieve(HttpContext $context, string $code) 
    {
        $code = RouteParamParser::positiveInt(['c' => $code], 'c');
        $model = $this->languagesService->retrieve($code);
        return $this->jsonObject($model);
    }

    public function fallback(HttpContext $context)
    {
        $code = $this->settings->i18n->default;
        $model = $this->languagesService->retrieve($code);
        return $this->jsonObject($model);
    }

    public function create(HttpContext $context)
    {
        $formData = $context->request->body();
        $code = $this->languagesService->create(LanguageForm::fromInput($formData));
        return $this->jsonCreatedId($code);
    }

    public function update(HttpContext $context, string $code)
    {
        $code = RouteParamParser::string(['c' => $code], 'c');
        $formData = $context->request->body();

        $this->languagesService->update(LanguageForm::fromInput($formData));
        return $this->jsonEmpty();
    }

    public function delete(HttpContext $context, string $code)
    {
        $code = RouteParamParser::string(['c' => $code], 'c');
        $this->languagesService->delete($code);
        return $this->jsonEmpty(204);
    }
}