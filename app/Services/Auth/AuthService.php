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
use RuntimeException;
use Throwable;

use PDO;    

final class AuthService
{
    public function __construct(
        private readonly UsersRepository $usersRepo,
        private readonly UnitOfWork $uow,
        private readonly Jwt $jwt,
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
            throw $this->translatePdoException($e);
        }
    }

    public function mailVerify(int $userId, string $token) : string
    {
        $isVerified = $this->mailVerificationService->verify($userId, $token);
        if (!$isVerified) throw new MailException('Mail verification failed');

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
        $this->passResetService->resetPassword($userId, password_hash($form->password, PASSWORD_DEFAULT));
    }

    private const UNIQUE_CONSTRAINTS = [
        'uq_users_username_lower' => 'username',
        'uq_users_email_lower'    => 'email',
    ];

    private const UNIQUE_MESSAGES = [
        'username' => 'Such username already exists',
        'email'    => 'The email already exists',
    ];

    /** @throws ValidationException */
    private function translatePdoException(\PDOException $e): Throwable
    {
        if ($e->getCode() !== '23505') {
            return $e;
        }

        $constraint = PdoExtensions::extractConstraintName($e->getMessage());

        if ($constraint === null) {
            return $e;
        }

        $field = self::UNIQUE_CONSTRAINTS[$constraint] ?? null;

        if ($field === null) {
            return $e;
        }

        return new ValidationException([
            $field => [self::UNIQUE_MESSAGES[$field]],
        ]);
    }
}