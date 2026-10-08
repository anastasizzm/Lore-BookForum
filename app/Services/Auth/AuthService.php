<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Constants;

use App\Repositories\Users\UsersRepository;

use App\Services\Configuration\UnitOfWork;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\PasswordResetService;

use App\Models\Email;

use App\Lib\Jwt;
use App\Lib\CsrfManager;

use App\Forms\Auth\RegisterForm;
use App\Forms\Auth\LoginForm;
use App\Forms\Auth\PassResetMailForm;
use App\Forms\Auth\PassResetForm;

use App\Extensions\PdoExtensions;

use App\Exceptions\ValidationException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\MailException;
use App\Exceptions\Translators\UserExceptionTranslator;
use RuntimeException;
use Throwable;

use PDO;    

final class AuthService
{
    public function __construct(
        private readonly UsersRepository $usersRepo,
        private readonly UnitOfWork $uow,
        private readonly Jwt $jwt,
        private readonly UserExceptionTranslator $translator,
        private readonly EmailVerificationService $mailVerificationService,
        private readonly PasswordResetService $passResetService
    ){}

    /** @throws UnauthorizedException */
    /** @throws ValidationException */
    /** @throws ForbiddenException */
    /** @throws MailException */
    public function login(LoginForm $form) : string
    {
        $errors = [];
        $isValid = $form->validate($errors);
        if (!$isValid) throw new ValidationException($errors);

        $credits = $this->usersRepo->findCreditsByLogin($form->login);
        if ($credits === null || !password_verify($form->password, $credits->password))
            throw new UnauthorizedException('Invalid login or password');

        if ($credits->isBlocked)
            throw new ForbiddenException('Account is blocked');

        return $this->jwt->access($credits->id, ['verified' => $credits->isVerified ? '1' : '0']);
    }

    /** @throws ValidationException */
    public function register(RegisterForm $form) : string
    {
        $errors = [];
        $isValid = $form->validate($errors);
        if (!$isValid) throw new ValidationException($errors);

        try{
            $userId = $this->uow->transactional(function (PDO $pdo) use ($form): int 
            {
                $userId = $this->usersRepo->create($form->username, $form->email, password_hash($form->password, PASSWORD_DEFAULT));
                $this->usersRepo->createProfile($userId, $form->name, $form->surname, '');
                $this->usersRepo->createRules($userId, false, false);
                return $userId;
            });

            $this->mailVerificationService->send($userId, $form->email);
            return $this->jwt->access($userId, ['verified' => '0']);
        }
        catch(\PDOException $e) {
            throw $this->translator->translate($e);
        }
    }

    public function resendMailVerify(string $oldToken) : string 
    {
        
    }

    public function mailVerify(string $token) : string
    {
        $userId = $this->mailVerificationService->verify($token);
        $this->uow->transactional(function (PDO $pdo) use ($userId): void 
        {
            $ok = $this->usersRepo->markEmailVerified($userId);
            if (!$ok) {
                $exists = $this->usersRepo->exists($userId);
                if (!$exists) {
                    throw new NotFoundException('User not found');
                }   
            }
        });

        return $this->jwt->access($userId, ['verified' => '1']);
    }

    public function startPasswordReset(PassResetMailForm $form) : void
    {
        $errors = [];
        if (!$form->validate($errors)) throw new ValidationException($errors);

        $credits = $this->usersRepo->findCreditsByLogin($form->email);
        if ($credits === null)
            throw new ValidationException(['email' => ['Account with this email not found']]);

        $this->passResetService->startReset($credits->id, $form->email);
    }

    public function resetPassword(PassResetForm $form) : void 
    {
        $errors = [];
        if (!$form->validate($errors)) throw new ValidationException($errors);

        $userId = $this->passResetService->verify($form->token);
        $this->usersRepo->updatePassword($userId, password_hash($form->password, PASSWORD_DEFAULT));
        $this->passResetService->resetTokens($userId);
    }
}