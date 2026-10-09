<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Constants;

use App\Repositories\Users\UsersRepository;

use App\Services\Configuration\UnitOfWork;
use App\Services\Auth\EmailVerificationService;

use App\Models\Email;
use App\Models\Auth\AuthCredits;

use App\Lib\Jwt;
use App\Lib\I18n\Translator;

use App\Validators\Auth\RegisterFormValidator;
use App\Validators\Auth\LoginFormValidator;

use App\Forms\Auth\RegisterForm;
use App\Forms\Auth\LoginForm;
use App\Forms\Auth\AccountCreditsForm;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\Translators\UserExceptionTranslator;

use PDO;    

final class AuthService 
{
     public function __construct(
        private readonly UsersRepository $usersRepo,
        private readonly UnitOfWork $uow,
        private readonly Jwt $jwt,
        private readonly UserExceptionTranslator $exceptionTranslator,
        private readonly EmailVerificationService $mailVerificationService,
        private readonly LoginFormValidator $loginFormValidator,
        private readonly RegisterFormValidator $registerFormValidator,
        private readonly Translator $translator
    ){}

    /** @throws UnauthorizedException */
    /** @throws ValidationException */
    /** @throws ForbiddenException */
    public function login(LoginForm $form) : string
    {
        $errorBag = $this->loginFormValidator->validateOne($form);
        if (!$errorBag->isEmpty()) throw new ValidationException($errorBag->all());

        $credits = $this->usersRepo->getAuthCredits($form->login);
        if (!$this->validateCredits($credits, $form))
            throw new UnauthorizedException($this->translator->t("errors.account.credits_invalid"));

        if ($credits->isBlocked)
            throw new ForbiddenException($this->translator->t("errors.account.account_block"));

        return $this->generateToken($credits->id, $credits->isVerified);
    }

    /** @throws ValidationException */
    public function register(RegisterForm $form) : string
    {
        $errorBag = $this->loginFormValidator->validateOne($form);
        if (!$errorBag->isEmpty()) throw new ValidationException($errorBag->all());

        try{
            $userId = $this->uow->transactional(function (PDO $pdo) use ($form): int 
            {
                $userId = $this->usersRepo->create($form->username, $form->email, password_hash($form->password, PASSWORD_DEFAULT));
                $this->usersRepo->createProfile($userId, $form->name, $form->surname, '');
                return $userId;
            });

            $this->mailVerificationService->startVerification($userId, $form->email);
            return $this->generateToken($userId, false);
        }
        catch(\PDOException $e) {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    private function validateCredits(?AuthCredits $credits, LoginForm $form) : bool 
    {
        if ($credits === null || !password_verify($form->password, $credits->password))
            return false;

        return true;
    }

    private function generateToken(int $userId, bool $isVerified) : string 
    {
        return $this->jwt->access($userId, ['verified' => $isVerified ? '1' : '0']);
    }
}