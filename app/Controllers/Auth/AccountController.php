<?php
declare(strict_types=1);

namespace App\Controllers\Auth;


use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;
use App\Http\HttpException;

use App\Controllers\Controller;

use App\Services\Configuration\CookieService;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\PasswordResetService;
use App\Services\Auth\AccountService;

use App\Exceptions\ValidationException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\MailException;
use App\Exceptions\ForbiddenException;
use App\Constants;

use App\Extensions\Parsers\RouteParamParser;

use App\Forms\Auth\AccountCreditsForm;
use App\Forms\Auth\MailOnlyForm;

use App\Models\Errors\InnerMessage;


final class AccountController extends Controller
{
    public function __construct(
        private readonly EmailVerificationService $mailVerify,
        private readonly PasswordResetService $passResetService,
        private readonly AccountService $accountService,
        private readonly CookieService $cookies,
        private readonly UrlGenerator $url
    ){}

    public function verifyMail(HttpContext $context, string $token)
    {
        $isVerified = $ctx->attribute(Constants::VERIFIED_ATTR) ?? false;
        if($isVerified) return Response::redirect($this->url->url('home'));

        try{
            $token = $this->mailVerify->completeVerification($token);
            $response = Response::redirect($this->url->url('home'));
            
            return $this->cookies->set($response, Constants::TOKEN_COOKIE, $token);
        }
        catch(HttpException $e){
            if ($e->getStatus() === 302) return Response::redirect($this->url->url('home'));
            return $this->render('auth/verify-result', ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getPasswordMail(HttpContext $ctx) : Response 
    {
        return $this->render('auth/password-email');
    }

    public function passwordMail(HttpContext $context) : Response 
    {
        $formData = $context->request->body();
        try{
            $this->passResetService->startReset(MailOnlyForm::fromInput($formData));
            return $this->render('message', ['message' => 'The link to reset your password was sent to your email']);
        }
        catch(ValidationException $e)
        {
            return $this->render('auth/password-email', ['form' => $formData, 'errors' => $e->errors()]);
        }
    }

    public function getPasswordReset(HttpContext $context, string $token) : Response 
    {
        return $this->render('auth/password-reset', ['token' => $token]);
    }

    public function passwordReset(HttpContext $context) : Response 
    {
        $formData = $context->request->body();
        try{
            $this->service->completeReset(PassResetForm::fromInput($formData));
            return $this->render('message', ['message' => 'The new password was successfully set', 'actionUrl' => $this->url->url('login'), 'Login']);
        }
        catch(ValidationException $e)
        {
            return $this->render('auth/password-reset', ['form' => $formData, 'errors' => $e->errors()]);
        }
        catch(HttpException $e)
        {
            return $this->render('message', ['statusCode' => $e->getStatus(), 'message' => $e->getMessage()]);
        }
    }
}