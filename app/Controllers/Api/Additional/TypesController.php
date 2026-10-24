<?php
declare(strict_types=1);

namespace App\Controllers\Api\Additional;

use App\Services\Additional\TypesService;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\TypeForm;

use App\Controllers\Controller;

use App\Http\HttpContext;

use App\Lib\I18n\Translator;
use App\Extensions\Parsers\RouteParamParser;
use App\Exceptions\NotFoundException;
use App\Exceptions\ConflictException;

use App\ErrorCodes;

final class TypesController extends Controller
{
    public function __construct(
        private readonly TypesService $typesService,
        private readonly Translator $translator
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

    public function create(HttpContext $context)
    {
        $form = TypeForm::fromInput($context->request->body());

        $id = $this->typesService->create($form);
        return $this->jsonCreatedId($id);
    }

    public function update(HttpContext $context, string $typeId)
    {
        $id = RouteParamParser::positiveInt(['id' => $typeId], 'id');
        $form = TypeForm::fromInput($context->request->body());

        $this->typesService->update($id, $form);
        return $this->jsonEmpty();
    }

    /**public function delete(HttpContext $context, string $typeId)
    {
        $id = RouteParamParser::positiveInt(['id' => $typeId], 'id');

        try {
            $deleted = $this->typesService->delete($id);
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
    }**/
}