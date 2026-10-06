<?php
declare(strict_types=1);

namespace App\Controllers\Api\Auth;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;
use App\Http\HttpException;

use App\Controllers\Controller;

use App\Services\Auth\EmailVerificationService;

use App\Models\Error;

use App\Constants;
use App\ErrorCodes;


final class AuthController extends Controller
{
    public function __construct(
        private readonly EmailVerificationService $emailService
    ){}

    public function sendMail(HttpContext $context)
    {
        $isVerified = $context->attribute(Constants::VERIFIED_ATTR) ?? false;
        if ($isVerified) return $this->jsonError(new Error(ErrorCodes::ALREADY_DONE, "You have already verified your email"), 409);
        
        $token = $context->input('token') ?? '';

        $this->emailService->reSend($token);
        return $this->jsonEmpty();
    }
}