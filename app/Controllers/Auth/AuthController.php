<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;
use App\Http\HttpException;

use App\Controllers\Controller;

use App\Services\Auth\AuthService;
use App\Services\Configuration\CookieService;

use App\Exceptions\ValidationException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\MailException;
use App\Exceptions\ForbiddenException;
use App\Constants;

use App\Forms\Auth\RegisterForm;
use App\Forms\Auth\LoginForm;
use App\Forms\Auth\PassResetMailForm;
use App\Forms\Auth\PassResetForm;

use App\Models\Errors\InnerMessage;

use App\Services\Users\UsersService;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $service,
        private readonly CookieService $cookies,
        private readonly UrlGenerator $url
    ){}

    public function getLogin(HttpContext $c) : Response {
        return $this->render('auth/login');
    }

    public function getRegister(HttpContext $c) : Response {
        return $this->render('auth/register');
    }

    public function register(HttpContext $ctx) : Response {
        $formData = $ctx->request->body();

        try{
            $token = $this->service->register(RegisterForm::fromInput($formData));
            $response = $this->render('message', ['message' => 'Please confirm your email to have full access', 'actionUrl' => $this->url->url('home'), 'actionTitle' => 'Start Reading']);
            return $this->cookies->set($response, Constants::TOKEN_COOKIE, $token);
        }
        catch(ValidationException $e){
            return $this->render('auth/register', ['form' => $formData, 'errors' => $e->errors()]);
        }
    }

    public function login(HttpContext $ctx) : Response {
        $formData = $ctx->request->body();

        try{
            $token = $this->service->login(LoginForm::fromInput($formData));
            $response = Response::redirect($this->url->url('home'));
            return $this->cookies->set($response, Constants::TOKEN_COOKIE, $token);
        }
        catch(UnauthorizedException $e){
            return $this->render('auth/login', ['form' => $formData, 'innerMessages' => [InnerMessage::asError("Authentication failed", $e->getMessage())]]);
        }
        catch(ForbiddenException $e){
            return $this->render('message', ['statusCode' => $e->getStatus(), 'message' => $e->getMessage(), 'actionUrl' => $this->url->url('login'), 'actionTitle' => 'Understood']);
        }
        catch(ValidationException $e){
            return $this->render('auth/login', ['form' => $formData, 'errors' => $e->errors()]);
        }
    }

    public function logout(HttpContext $c) : Response{
        $response = Response::redirect($this->url->url('login'));
        return $this->cookies->clear($this->cookies->clear($response, Constants::CSRF_COOKIE, false), Constants::TOKEN_COOKIE);
    }

    public function mailVerify(HttpContext $ctx, string $token) : Response {
        $userId = $ctx->attribute(Constants::USER_ID_ATTR);
        if (empty($userId)) return Response::redirect($this->url->url('login'));

        $isVerified = $ctx->attribute(Constants::VERIFIED_ATTR) ?? false;
        if($isVerified) return Response::redirect($this->url->url('home'));

        try{
            $token = $this->service->mailVerify($userId, $token);
            $response = Response::redirect($this->url->url('home'));
            
            return $this->cookies->set($response, Constants::TOKEN_COOKIE, $token);
        }
        catch(HttpException | MailException $e){
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
            $this->service->startPasswordReset(PassResetMailForm::fromInput($formData));
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
            $this->service->resetPassword(PassResetForm::fromInput($formData));
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