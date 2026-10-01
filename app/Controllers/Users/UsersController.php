<?php
declare(strict_types=1);

namespace App\Controllers\Users;

use App\Controllers\Controller;

use App\Services\Users\UsersService;

use App\Http\HttpContext;
use App\Http\Response;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;

use App\Constants;

final class UsersController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService
    ){}

    public function retrieve(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect('login');

        $userContext = $this->usersService->loadContext($currentUserId);
        $userData = $this->usersService->retrieve($userId);
        if ($userData === null)
            return $this->render('message', ['message' => 'The profile is not found', 'statusCode' => 404]);

        return $this->render('profile/profile', ['user' => $userContext, 'userData' => $userData]);
    }

    public function getEdit(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;

        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return Response::redirect('login');

        $userContext = $this->usersService->loadContext($currentUserId);
        $userData = $this->usersService->retrieve($userId);
        if ($userData === null)
            return $this->render('message', ['message' => 'The profile is not found', 'statusCode' => 404]);

        return $this->render('profile/profile-edit', ['user' => $userContext, 'userData' => $userData]);
    }

}