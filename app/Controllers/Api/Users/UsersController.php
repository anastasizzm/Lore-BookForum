<?php
declare(strict_types=1);

namespace App\Controllers\Api\Users;

use App\Controllers\Controller;

use App\Services\Users\UsersService;

use App\Http\HttpContext;
use App\Lib\I18n\Translator;
use App\Models\Queries\Users\UsersListQuery;
use App\Exceptions\ValidationException;
use App\Exceptions\ForbiddenException;
use App\Extensions\Parsers\RouteParamParser;

use App\Forms\Users\ProfileForm;
use App\Constants;

final class UsersController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly Translator $translator
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
        return $this->jsonList($paginatedList->getArray(), [
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