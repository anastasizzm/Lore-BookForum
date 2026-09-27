<?php
declare(strict_types=1);

namespace App\Services;

use App\Constants;

use App\Repositories\UsersRepository;
use App\Repositories\ProfilesRepository;

use App\Services\UnitOfWork;
use App\Services\EmailVerificationService;

use App\Models\Email;

use App\Lib\Jwt;
use App\Lib\CsrfManager;

use App\Exceptions\ValidationException;
use App\Exceptions\OperationFailedException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\MailException;
use RuntimeException;
use Throwable;

final class AuthService
{
    public function __construct(
        private readonly UsersRepository $usersRepo,
        private readonly ProfilesRepository $profilesRepo,
        private readonly UnitOfWork $uof,
        private readonly Jwt $jwt,
        private readonly EmailVerificationService $verificationService
    ){}

    /** @throws UnauthorizedException */
    /** @throws ForbiddenException */
    /** @throws MailException */
    public function login($form) : string
    {
        $login = $form['login'];
        $pass = $form['password'];

        $credits = $this->usersRepo->findCreditsByLogin($login);
        $passHash = password_hash($pass, PASSWORD_BCRYPT);
        
        if ($credits === null || !password_verify($passHash, $credits['pass_hash']))
            throw new UnauthorizedException('Invalid login or password');

        if ($credits['is_blocked'])
            throw new ForbiddenException('Account is blocked');

        if (!$credits['is_verified'])
            throw new MailException();
        
        return $this->jwt->access($credits['id'], ['username' => $credits['username']]);
    }

    /** @throws ValidationException */
    public function register(array $form) : string
    {
        $errors = $this->validateRegisterForm($form);
        if ($errors !== []) throw new ValidationException($errors);

        $username = $form['username'];
        $email = $form['email'];
        $pass_hash = password_hash($form['password'], PASSWORD_BCRYPT);

        $name = $form['name'];
        $surname = $form['surname'];
        $bio = ''; // No bio input in register yet

        try{
            $userId = $this->uow->transactional(function (PDO $pdo) use ($username, $email, $pass_hash, $name, $surname, $bio): int 
            {
                $userId = $this->usersRepo->create($username, $email, $pass_hash);
                $this->profiles->create($userId, $name, $surname, $bio);
                return $userId;
            });

            $verificationService->send($userId, $email);
            return $this->jwt->access($userId, ['username' => $username]);
        }
        catch(PDOException $e) {
            throw $this->translatePdoException($e);
        }
    }

    public function mailVerify(string $token) : bool{
        return $verificationService->verify($token);
    }

    private function translatePdoException(PDOException $e): Throwable
    {
        if ($e->getCode() !== '23505') {
            return $e;
        }

        $constraint = $this->extractConstraintName($e->getMessage());

        $field = self::UNIQUE_CONSTRAINTS[$constraint] ?? null;

        if ($field === null) {
            return $e;
        }

        $message = match ($field) {
            'username' => 'Username already exists',
            'email'    => 'Email already exists',
            default    => 'Value already exists',
        };

        return new ValidationException([$field => [$message]]);
    }

    private const USERNAME_REGEX = '#^[A-Za-z0-9_\.-]{3,}$#';
    private const EMAIL_REGEX = '#^\w+@[A-Za-z]+\.[A-Za-z]+$#';

    private function validateRegisterForm(array $form) : array{
        $errors = [];

        $username = $form['username'];
        $email = $form['email'];

        if (!preg_match(self::USERNAME_REGEX, $username))
            $errors['username'][] = 'Invalid username format';

        if (!preg_match(self::EMAIL_REGEX, $email))
            $errors['email'][] = 'Invalid email format';

        if ($form['name'] == '')
            $errors['name'][] = 'Name is required';

        if ($form['surname'] = '')
            $errors['surname'][] = 'Surname is required';

        return $errors;
    }
}