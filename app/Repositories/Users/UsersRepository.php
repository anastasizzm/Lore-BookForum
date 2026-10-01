<?php
declare(strict_types=1);

namespace App\Repositories\Users;

use App\Models\Auth\AuthCredits;
use App\Models\Users\UserContext;
use App\Models\Users\UserData;

use App\Repositories\Repository;
use App\Lib\Data\Database;

use PDO;

final class UsersRepository extends Repository
{
    public function __construct(Database $db){
        parent::__construct($db);
    }

    public function findCreditsByLogin(string $login): ?AuthCredits
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, pass_hash, is_blocked, is_verified, username
            FROM users
            WHERE email = :email OR username = :username
            LIMIT 1'
        );
        $stmt->execute([':email' => $login, ':username' => $login]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : AuthCredits::fromRow($row);
    }

    public function exists(int $id) : bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return (bool)$stmt->fetchColumn();
    }

    public function create($username, $email, $passHash) : int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (username, email, pass_hash)
             VALUES (:username, :email, :passHash)
             RETURNING id'
        );

        $stmt->execute([':username' => $username, ':email' => $email, ':passHash' => $passHash]);

        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException('No instance created');
        }
        
        return (int)$id;
    }

    public function createProfile(int $userId, string $name, string $surname, string $bio) : void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO profiles (user_id, name, surname, bio)
            VALUES (:userId, :name, :surname, :bio)'
        );

        $stmt->execute([':userId' => $userId, ':name' => $name, ':surname' => $surname, ':bio' => $bio]);
    }

    public function createRules(int $userId, bool $isAdmin, bool $isRedactor) 
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users_rules (user_id, is_redactor, is_admin)
            VALUES (:userId, :isRedactor, :isAdmin)'
        );

        $stmt->bindValue(':userId',     $userId,     PDO::PARAM_INT);
        $stmt->bindValue(':isRedactor', $isRedactor, PDO::PARAM_BOOL);
        $stmt->bindValue(':isAdmin',    $isAdmin,    PDO::PARAM_BOOL);
        $stmt->execute();
    }

    public function markEmailVerified(int $id): bool
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE users
            SET is_verified = true
            WHERE id = :id
            AND is_verified = false
            RETURNING id'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    public function loadContext(int $userId) : ?UserContext
    {
        $stmt = $this->pdo()->prepare(
            'SELECT u.id, u.username, p.name, p.surname, p.avatar, ur.is_admin, ur.is_redactor
            FROM (SELECT u0.id, u0.username FROM users u0 WHERE u0.id = :userId) u
            INNER JOIN profiles p ON p.user_id = u.id
            INNER JOIN users_rules ur ON ur.user_id = u.id'
        );
        $stmt->execute([':userId' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : UserContext::fromRow($row);
    }

    public function retrieve(int $userId) : ?UserData
    {
        $sql = "
            SELECT
                u.id,
                u.username,
                u.created_at,
                u.email,
                p.name,
                p.surname,
                p.bio,
                p.avatar
            FROM users u
            INNER JOIN profiles p ON p.user_id = u.id
            WHERE u.id = :userId
            LIMIT 1
        ";

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':userId' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : UserData::fromRow($row);
    }

    public function edit(
        int $userId,
        string $email,
        string $username,
    ) : void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE users SET email = :email, username = :username
            WHERE id = :userId'
        );

        $stmt->execute([':userId' => $userId, ':email' => $email, ':username' => $username]);
    }

    public function editProfile(
        int $userId,
        string $name,
        string $surname,
        string $bio,
        string $avatar,
    ) : void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE profiles SET name = :name, surname = :surname, bio = :bio, avatar = :avatar
            WHERE user_id = :userId'
        );

        $stmt->execute([':userId' => $userId, ':name' => $name, ':surname' => $surname, ':bio' => $bio, ':avatar' => $avatar]);
    }
}