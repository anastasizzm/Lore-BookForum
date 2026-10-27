<?php
declare(strict_types=1);

namespace App\Controllers\Api\Additional;

use App\Services\Additional\CategoriesService;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\CategoryForm;

use App\Controllers\Controller;

use App\Http\HttpContext;

use App\Lib\I18n\Translator;
use App\Extensions\Parsers\RouteParamParser;
use App\Exceptions\NotFoundException;
use App\Exceptions\ConflictException;

use App\ErrorCodes;

final class CategoriesController extends Controller
{
    public function __construct(
        private readonly CategoriesService $categoriesService,
        private readonly Translator $translator
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

    public function create(HttpContext $context)
    {
        $form = CategoryForm::fromInput($context->request->body());

        $id = $this->categoriesService->create($form);
        return $this->jsonCreatedId($id);
    }

    public function update(HttpContext $context, string $categoryId)
    {
        $id = RouteParamParser::positiveInt(['id' => $categoryId], 'id');
        $form = CategoryForm::fromInput($context->request->body());

        $this->categoriesService->update($id, $form);

        return $this->jsonEmpty();
    }

    public function delete(HttpContext $context, string $categoryId)
    {
        $id = RouteParamParser::positiveInt(['id' => $categoryId], 'id');

        $this->categoriesService->delete($id);
        return $this->jsonEmpty(204);
    }
}