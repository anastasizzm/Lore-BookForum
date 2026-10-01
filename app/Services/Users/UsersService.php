<?php
declare(strict_types=1);

namespace App\Services\Users;

use App\Cache\UserContextCache;

use App\Forms\Users\UserForm;

use App\Services\Configuration\UnitOfWork;

use App\Models\Users\UserContext;
use App\Models\Users\UserData;
use App\Repositories\Users\UsersRepository;
use Throwable;
use PDO;

final class UsersService
{
    public function __construct(
        private readonly UserContextCache $cache,
        private readonly UnitOfWork $uow,
        private readonly UsersRepository  $usersRepo,
    ){}

    public function loadContext(int $userId): ?UserContext
    {
        $user = null;

        try {
            $user = $this->cache->get($userId);
        } catch (Throwable $e) {
            error_log('[redis] user_ctx get failed: ' . $e->getMessage());
        }

        if ($user !== null) {
            return $user;
        }

        $user = $this->usersRepo->loadContext($userId);

        if ($user === null) {
            return null;
        }

        try {
            $this->cache->set($user, ttl: 300);
        } catch (Throwable $e) {
            error_log('[redis] user_ctx set failed: ' . $e->getMessage());
        }

        return $user;
    }

    public function forgetContext(int $userId): void
    {
        try {
            $this->cache->forget($userId);
        } catch (Throwable $e) {
            error_log('[redis] user_ctx forget failed: ' . $e->getMessage());
        }
    }

    public function retrieve(int $userId) : ?UserData
    {
        return $this->usersRepo->retrieve($userId);
    }

    public function edit(int $userId, UserForm $form)
    {
        $errors = [];
        $isValid = $form->validate($errors);
        if (!$isValid) throw new ValidationException($errors);

        try{
            $this->uow->transactional(function (PDO $pdo) use ($userId, $form) : void {
                $this->usersRepo->edit($userId, $form->email, $form->username);
                $this->usersRepo->editProfile($userId, $form->name, $form->surname, $form->bio, $form->avatar);
            });
        }
        catch(\PDOException $e){
            throw $this->translatePdoException($e);
        }
    }

    private const CHECK_CONSTRAINTS = [
        'users_username_check' => 'username'
    ];

    private const CHECK_MESSAGES = [
        'username' => 'Invalid format'
    ];

    private function translatePdoException(\PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());
        if ($constraint === null) {
            return $e;
        }

        if ($e->getCode() == '23514'){
            $field = self::CHECK_CONSTRAINTS[$constraint] ?? null;
            if ($field === null)
                return $e;

            return new ValidationException([
                $field => [self::CHECK_MESSAGES[$field]],
            ]);
        }

        return $e;
    }
}