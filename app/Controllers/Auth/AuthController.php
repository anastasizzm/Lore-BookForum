<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Http\Request;
use App\Http\Response;
use App\Http\UrlGenerator;
use App\Http\HttpException;

use App\Lib\Controller;

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

use App\Models\InnerMessageType;
use App\Models\InnerMessage;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $service,
        private readonly CookieService $cookies,
        private readonly UrlGenerator $url
    ){}

    public function getLogin(Request $request) : Response {
        return $this->render('auth/login');
    }

    public function getRegister(Request $request) : Response {
        return $this->render('auth/register');
    }

    public function register(Request $request) : Response {
        $formData = $request->body();

        try{
            $this->service->register(RegisterForm::fromArray($formData));
            return $this->render('message', ['message' => 'Please confirm your email before login', 'actionUrl' => $url->url('login'), 'actionTitle' => 'Confirmed']);
        }
        catch(ValidationException $e){
            return $this->render('auth/register', ['form' => $formData, 'errors' => $e->errors()]);
        }
    }

    public function login(Request $request) : Response {
        $formData = $request->body();

        try{
            $token = $this->service->login(LoginForm::fromArray($formData));
            $response = Response::redirect($url->url('home'));
            return $cookies->set($response, Constants::TOKEN_COOKIE, $token);
        }
        catch(UnauthorizedException $e){
            return $this->render('auth/login', ['form' => $formData, 'innerMessages' => [new InnerMessage(InnerMessageType::Error, "Authentication failed", $e->getMessage())]]);
        }
        catch(MailException $e){
            return $this->render('auth/login', ['form' => $formData, 'innerMessages' => [new InnerMessage(InnerMessageType::Error, "Verification failed", 'Please verify your email first')]]);
        }
        catch(ForbiddenException $e){
            return $this->render('message', ['statusCode' => $e->getStatus(), 'message' => $e->getMessage(), 'actionUrl' => $url->url('login'), 'actionTitle' => 'Understood']);
        }
    }

    public function logout(Request $request) : Response{
        $response = Response::redirect($url->url('login'));
        return $cookies->clear($cookies->clear($response, Constants::CSRF_COOKIE, false), Constants::TOKEN_COOKIE);
    }

    public function mailVerify(Request $request, string $token) : Response {
        try{
            if (!$this->service->mailVerify($token))
                throw new MailException('Mail verification failed');

            return $this->render('auth/verify-result', ['success' => true, 'message' => 'Email successfully verified. You can now Sign In']);
        }
        catch(HttpException | MailException $e){
            return $this->render('auth/verify-result', ['success' => false, 'message' => $e->getMessage()]);
        }
    }
}