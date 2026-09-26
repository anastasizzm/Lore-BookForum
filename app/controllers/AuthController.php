<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\UrlGenerator;

use App\Lib\Controller;

use App\Services\AuthService;
use App\Services\CookieService;

use App\Exceptions\ValidationException;
use App\Exceptions\OperationFailedException;
use App\Constants;

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
            $token = $this->service->register($formData);
            $response = Response::redirect($url->url('home'));
            $cookies->set($response, Constants::TOKEN_COOKIE, $token);
            return $respose;
        }
        catch(ValidationException $e){
            return $this->render('auth/register', ['form' => $formData, 'errors' => $e->errors()]);
        }
        catch(OperationFailedException $e){
            return $this->render('auth/register', ['form' => $formData, 'innerMessages' => [new InnerMessage(InnerMessageType::Error, $e->title, $e->message)]]);
        }
    }
}