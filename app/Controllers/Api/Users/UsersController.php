<?php
declare(strict_types=1);

namespace App\Controllers\Api\Users;

use App\Controllers\Controller;

use App\Services\Users\UsersService;

use App\Http\HttpContext;
use App\Exceptions\ValidationException;

use App\Forms\Users\UserForm;
use App\Constants;

final class UsersController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService
    ){}

    public function edit(HttpContext $context, string $userId)
    {
        $userId = (int)$userId;

        $formData = $context->request->body();
        try{
            $this->usersService->edit($userId, UserForm::fromInput($formData));
            return $this->jsonEmpty(201);
        }
        catch(ValidationException $e){
            return $this->jsonValidationErrors($e->errors());
        }

    }
}