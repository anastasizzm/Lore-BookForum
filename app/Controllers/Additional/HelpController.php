<?php
declare(strict_types=1);

namespace App\Controllers\Additional;

use App\Controllers\Controller;

use App\Services\Users\UsersService;

use App\Http\HttpContext;
use App\Http\Response;

use App\Constants;

final class HelpController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
    ){}

    public function getHelp(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR) ?? NULL;
        $userContext = $userId === NULL ? NULL : $this->usersService->loadContext($userId);

        return $this->render('help', [
            'user' => $userContext
        ]);
    }

    public function getPrivacy(HttpContext $context){
        $userId = $context->attribute(Constants::USER_ID_ATTR) ?? NULL;
        $userContext = $userId === NULL ? NULL : $this->usersService->loadContext($userId);

        return $this->render('privacy', [
            'user' => $userContext
        ]);
    }
}