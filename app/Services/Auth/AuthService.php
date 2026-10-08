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
        private readonly UserExceptionTranslator $translator,
        private readonly EmailVerificationService $mailVerificationService
    ){}

    /** @throws UnauthorizedException */
    /** @throws ValidationException */
    /** @throws ForbiddenException */
    public function login(LoginForm $form) : string
    {
        $errors = [];
        $isValid = $form->validate($errors);
        if (!$isValid) throw new ValidationException($errors);

        $credits = $this->usersRepo->getAuthCredits($form->login);
        if (!$this->validateCredits($credits, $form))
            throw new UnauthorizedException('Invalid login or password');

        if ($credits->isBlocked)
            throw new ForbiddenException('Account is blocked');

        return $this->generateToken($credits->id, $credits->isVerified);
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
                return $userId;
            });

            $this->mailVerificationService->startVerification($userId, $form->email);
            return $this->generateToken($userId, false);
        }
        catch(\PDOException $e) {
            throw $this->translator->translate($e);
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
        return $this->jwt->access($userid, ['verified' => $isVerified ? '1' : '0']);
    }
}