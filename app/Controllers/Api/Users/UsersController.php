<?php
declare(strict_types=1);

namespace App\Controllers\Api\Users;

use App\Controllers\Controller;

use App\Services\Users\UsersService;
use App\Services\Permissions\SystemPermissionsService;

use App\Http\HttpContext;
use App\Lib\I18n\Translator;
use App\Models\Queries\Users\UsersListQuery;
use App\Models\Queries\PaginationQuery;
use App\Exceptions\ValidationException;
use App\Exceptions\ForbiddenException;
use App\Extensions\Parsers\RouteParamParser;

use App\Forms\Users\ProfileForm;
use App\Constants;

final class UsersController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly Translator $translator,
        private readonly SystemPermissionsService $permissionsService
    ){}

    public function editProfile(HttpContext $context, string $userId)
    {
        $userId = RouteParamParser::positiveInt(['userId' => $userId], 'userId');

        $formData = $context->request->body();
        $this->usersService->editProfile($userId, ProfileForm::fromInput($formData));
        return $this->jsonEmpty(201);
    }

    public function list(HttpContext $context)
    {
        $query = UsersListQuery::fromInput($context->request->query);
        $paginatedList = $this->usersService->getList($query);

        // Админской таблице нужны назначенные роли уже в списке (колонка
        // Role). Раньше /api/users не отдавал roles: первая страница из
        // серверного bootstrap показывала роли, а после поиска/«показать ещё»
        // колонка становилась пустой. Обогащаем каждый элемент ролями.
        $items = array_map(function ($item) {
            $data = method_exists($item, 'toArray') ? $item->toArray() : (array) $item;
            $id = (int) ($data['id'] ?? 0);
            $data['roles'] = [];
            if ($id > 0) {
                $roles = $this->permissionsService
                    ->getUserRoles($id, new PaginationQuery(1, 50))
                    ->getArray();
                $data['roles'] = array_map(static fn ($role) => [
                    'id' => $role->roleId,
                    'title' => $role->title,
                    'color' => $role->color,
                ], $roles);
            }
            return $data;
        }, $paginatedList->getArray());

        return $this->jsonList($items, [
            'page' => $paginatedList->getPage(),
            'pageSize' => $paginatedList->getPageSize(),
            'hasNext' => $paginatedList->hasNext(),
        ]);
    }

    public function block(HttpContext $context, string $userId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $userId = RouteParamParser::positiveInt(['userId' => $userId], 'userId');

        if ($userId === $currentUserId)
            throw new ForbiddenException($this->translator->t('errors.policies.access.account'));

        $this->usersService->block($userId);
        return $this->jsonEmpty();
    }
}