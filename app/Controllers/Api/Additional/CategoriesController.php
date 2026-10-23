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
use App\Models\Errors\ErrorBag;
use App\Models\Errors\ValidationError;

use App\ErrorCodes;
use App\Constants;
use App\Exceptions\ConflictException;

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
        $bag = new ErrorBag();

        $id = $this->categoriesService->create($form, $bag);
        if ($id === null) {
            return $this->jsonError(new ValidationError($bag->all()), 422);
        }

        return $this->jsonCreatedId($id);
    }

    public function update(HttpContext $context, string $categoryId)
    {
        $id = RouteParamParser::positiveInt(['id' => $categoryId], 'id');
        $form = CategoryForm::fromInput($context->request->body());
        $bag = new ErrorBag();

        if (!$this->categoriesService->update($id, $form, $bag)) {
            if (!$bag->isEmpty()) {
                return $this->jsonError(new ValidationError($bag->all()), 422);
            }
            throw new NotFoundException(
                $this->translator->t('errors.common.not_found'),
                ErrorCodes::NOT_FOUND,
            );
        }

        return $this->jsonEmpty();
    }

    public function delete(HttpContext $context, string $categoryId)
    {
        $id = RouteParamParser::positiveInt(['id' => $categoryId], 'id');

        try {
            $deleted = $this->categoriesService->delete($id);
        } catch (ConflictException) {
            return $this->jsonError(
                new \App\Models\Errors\Error(
                    $this->translator->t('errors.common.in_use'),
                    ErrorCodes::CONFLICT,
                ),
                409
            );
        }

        if (!$deleted) {
            throw new NotFoundException(
                $this->translator->t('errors.common.not_found'),
                ErrorCodes::NOT_FOUND,
            );
        }

        return $this->jsonEmpty();
    }
}