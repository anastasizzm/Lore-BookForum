<?php
declare(strict_types=1);

namespace App\Controllers\Api\Additional;

use App\Services\Additional\RolesService;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\UserRoleForm;

use App\Controllers\Controller;

use App\Http\HttpContext;

use App\Lib\I18n\Translator;
use App\Extensions\Parsers\RouteParamParser;
use App\Exceptions\NotFoundException;
use App\Exceptions\ConflictException;

use App\ErrorCodes;

final class RolesController extends Controller
{
    public function __construct(
        private readonly RolesService $rolesService,
        private readonly Translator $translator
    ){}

    public function list(HttpContext $context)
    {
        $query = BasicModelListQuery::fromInput($context->request->query);
        $paginatedList = $this->rolesService->getList($query);
        return $this->jsonList($paginatedList->getArray(), [
                'page' => $paginatedList->getPage(),
                'pageSize' => $paginatedList->getPageSize(),
                'hasNext' => $paginatedList->hasNext(),
            ]);
    }

    public function create(HttpContext $context)
    {
        $form = UserRoleForm::fromInput($context->request->body());

        $id = $this->rolesService->create($form);
        return $this->jsonCreatedId($id);
    }

    public function update(HttpContext $context, string $roleId)
    {
        $id = RouteParamParser::positiveInt(['id' => $roleId], 'id');
        $form = UserRoleForm::fromInput($context->request->body());

        $this->rolesService->update($id, $form);
        return $this->jsonEmpty();
    }

    public function delete(HttpContext $context, string $roleId)
    {
        $id = RouteParamParser::positiveInt(['id' => $roleId], 'id');

        $this->rolesService->delete($id);
        return $this->jsonEmpty(204);
    }
}