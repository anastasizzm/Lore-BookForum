<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;
use App\Http\HttpException;
use App\Lib\I18n\Translator;

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
        private readonly Translator $translator,
        private readonly CookieService $cookies,
        private readonly UrlGenerator $url
    ){}

    public function getLogin(HttpContext $c) : Response {
        $redirectUrl = $c->query('redirect');
        return $this->render('auth/login', ['redirectUrl' => $redirectUrl]);
    }

    public function getRegister(HttpContext $c) : Response {
        return $this->render('auth/register');
    }

    public function register(HttpContext $ctx) : Response {
        $formData = $ctx->request->body();
        $form = RegisterForm::fromInput($formData);

        try{
            [$token, $userId] = $this->service->register($form);
            $response = $this->render('message', ['message' => $this->translator->t('common.email.confirm_msg'), 'actionUrl' => $this->url->url('home'), 'actionTitle' => $this->translator->t("common.drafts.continue")]);

            try{
                $this->mailVerify->startVerification($userId, $form->email);
            }
            catch(MailException $e){
                $response = $this->render('message', ['message' => $this->translator->t('errors.verify.send_fail'), 'actionUrl' => $this->url->url('home'), 'actionTitle' => $this->translator->t("common.drafts.continue")]);
            }

            return $this->cookies->set($response, Constants::TOKEN_COOKIE, $token);
        }
        catch(ValidationException $e){
            return $this->render('auth/register', ['form' => $formData, 'errors' => $e->errors()]);
        }
    }

    public function login(HttpContext $ctx) : Response {
        $formData = $ctx->request->body();
        $redirectUrl = $ctx->query('redirect') ?? $this->url->url('home');

        try{
            $token = $this->service->login(LoginForm::fromInput($formData));
            $response = Response::redirect($redirectUrl);
            return $this->cookies->set($response, Constants::TOKEN_COOKIE, $token);
        }
        catch(UnauthorizedException $e){
            return $this->render('auth/login', ['form' => $formData, 'innerMessages' => [InnerMessage::asError("Authentication failed", $e->getMessage())]]);
        }
        catch(ForbiddenException $e){
            return $this->render('message', ['statusCode' => $e->getStatus(), 'message' => $e->getMessage(), 'actionUrl' => $this->url->url('login') . "?redirect=" . $redirectUrl, 'actionTitle' => $this->translator->t("common.drafts.continue")]);
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