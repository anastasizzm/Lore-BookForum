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
use App\Services\Auth\EmailVerificationService;

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


final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $service,
        private readonly EmailVerificationService $mailVerify,
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
}