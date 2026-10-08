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
use App\Services\Auth\AccountService;

use App\Exceptions\ValidationException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\MailException;
use App\Exceptions\ForbiddenException;
use App\Constants;

use App\Extensions\Parsers\RouteParamParser;

use App\Forms\Auth\AccountCreditsForm;

use App\Models\Errors\InnerMessage;


final class AccountController extends Controller
{
    public function __construct(
        private readonly EmailVerificationService $mailVerify,
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
}