<?php
declare(strict_types=1);

namespace App\Controllers\Api\Auth;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;
use App\Http\HttpException;
use App\Lib\I18n\Translator;

use App\Controllers\Controller;

use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\AccountService;

use App\Models\Error;

use App\Constants;
use App\ErrorCodes;


final class AccountController extends Controller
{
    public function __construct(
        private readonly EmailVerificationService $emailService,
        private readonly AccountService $accountService,
        private readonly Translator $translator
    ){}

    public function resendMailVerify(HttpContext $context)
    {
        $isVerified = $context->attribute(Constants::VERIFIED_ATTR) ?? false;
        if ($isVerified) return $this->jsonError(new Error(ErrorCodes::ALREADY_DONE, $this->translator->t("errors.account.mail_already_verified")), 409);
        
        $token = $context->input('token') ?? '';
        $this->emailService->restartVerification($token);
        return $this->jsonEmpty();
    }

    public function changeCredits(HttpContext $context, string $userId)
    {
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $formData = $context->request->body();

        try 
        {
            $this->accountService->changeCredits($userId, AccountCreditsForm::fromInput($formData));
            return $this->jsonEmpty(201);
        }
        catch(HttpException $e)
        {
            if ($e->getStatus() === 202)
                return $this->jsonObjectFromArray(['message' => $e->getMessage(), 'code' => $e->getErrorCode()], 202);
            
            return $this->jsonError($e->toError(), $e->getStatus());
        }
    }
}