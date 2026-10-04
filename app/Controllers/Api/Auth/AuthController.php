<?php
declare(strict_types=1);

namespace App\Controllers\Api\Auth;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;
use App\Http\HttpException;

use App\Controllers\Controller;

use App\Services\Auth\EmailVerificationService;
use App\Services\Users\UsersService;

use App\Models\Error;

use App\Constants;
use App\ErrorCodes;


final class AuthController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly EmailVerificationService $emailService
    ){}

    public function sendMail(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $isVerified = $context->attribute(Constants::VERIFIED_ATTR);
        if ($isVerified) return $this->jsonError(new Error(ErrorCodes::ALREADY_DONE, "You have already verified your email"), 409);

        $data = $this->usersService->retrieve($userId);
        if ($data === null)
            return $this->jsonError(new Error(ErrorCodes::NOT_FOUND, "Account not found. Register first"), 401);

        $this->emailService->send($userId, $data->email);
        return $this->jsonEmpty();
    }
}