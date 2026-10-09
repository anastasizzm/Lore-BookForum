<?php
declare(strict_types=1);

namespace App\Controllers\Api\Users;

use App\Controllers\Controller;

use App\Services\Users\UsersService;

use App\Http\HttpContext;
use App\Exceptions\ValidationException;
use App\Extensions\Parsers\RouteParamParser;

use App\Forms\Users\UserForm;
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
        $this->usersService->editProfile($userId, UserForm::fromInput($formData));
        return $this->jsonEmpty(201);
    }
}