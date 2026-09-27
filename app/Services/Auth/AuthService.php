<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Constants;

use App\Repositories\Users\UsersRepository;
use App\Repositories\Users\ProfilesRepository;

use App\Services\Configuration\UnitOfWork;
use App\Services\Auth\EmailVerificationService;

use App\Models\Email;

use App\Lib\Jwt;
use App\Lib\CsrfManager;

use App\Forms\Auth\RegisterForm;
use App\Forms\Auth\LoginForm;

use App\Exceptions\ValidationException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\MailException;
use RuntimeException;
use Throwable;

use PDO;    

final class AuthService
{
    public function __construct(
        private readonly UsersRepository $usersRepo,
        private readonly ProfilesRepository $profilesRepo,
        private readonly UnitOfWork $uow,
        private readonly Jwt $jwt,
        private readonly EmailVerificationService $verificationService
    ){}

    /** @throws UnauthorizedException */
    /** @throws ForbiddenException */
    /** @throws MailException */
    public function login(LoginForm $form) : string
    {
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
        $errors = $form->validate();
        if (!empty($errors)) throw new ValidationException($errors);

        try{
            $userId = $this->uow->transactional(function (PDO $pdo) use ($form): int 
            {
                $userId = $this->usersRepo->create($form->username, $form->email, password_hash($form->password, PASSWORD_DEFAULT));
                $this->profilesRepo->create($userId, $form->name, $form->surname, '');
                return $userId;
            });

            $this->verificationService->send($userId, $form->email);
            return $this->jwt->access($userId, ['verified' => '0']);
        }
        catch(\PDOException $e) {
            throw $this->translatePdoException($e);
        }
    }

    public function mailVerify(int $userId, string $token) : string
    {
        $isVerified = $this->verificationService->verify($userId, $token);
        if (!$isVerified) throw new MailException('Mail verification failed');

        return $this->jwt->access($userId, ['verified' => '1']);
    }

    private const UNIQUE_CONSTRAINTS = [
        'users_username_unique' => 'username',
        'users_email_unique'    => 'email',
    ];

    private const UNIQUE_MESSAGES = [
        'username' => 'Такое имя уже занято',
        'email'    => 'Такой email уже зарегистрирован',
    ];

    /** @throws ValidationException */
    private function translatePdoException(\PDOException $e): Throwable
    {
        if ($e->getCode() !== '23505') {
            return $e;
        }

        $constraint = $this->extractConstraintName($e->getMessage());

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

    private function extractConstraintName(string $message): ?string
    {
        if (preg_match('/constraint "([^"]+)"/', $message, $m)) {
            return $m[1];
        }

        return null;
    }
}