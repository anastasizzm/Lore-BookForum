<?php
declare(strict_types=1);

namespace App\Controllers\Api\Users;

use App\Controllers\Controller;

use App\Services\Users\UsersService;

use App\Http\HttpContext;
use App\Models\Queries\Users\UsersListQuery;
use App\Exceptions\ValidationException;
use App\Extensions\Parsers\RouteParamParser;

use App\Forms\Users\ProfileForm;
use App\Constants;

final class UsersController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService
    ){}

    public function editProfile(HttpContext $context, string $userId)
    {
        $userId = RouteParamParser::int(['userId' => $userId], 'userId');

        $formData = $context->request->body();
        $this->usersService->editProfile($userId, ProfileForm::fromInput($formData));
        return $this->jsonEmpty(201);
    }

    public function list(HttpContext $context)
    {
        $query = UsersListQuery::fromInput($context->request->query);
        $paginatedList = $this->usersService->getList($query);
        return $this->jsonList($paginatedList->getArray(), [
            'page' => $paginatedList->getPage(),
            'pageSize' => $paginatedList->getPageSize(),
            'hasNext' => $paginatedList->hasNext(),
        ]);
    }
}