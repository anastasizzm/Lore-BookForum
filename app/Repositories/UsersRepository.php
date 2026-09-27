<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Repository;
use App\Lib\Database;

use PDO;

final class UsersRepository extends Repository
{
    public function __construct(Database $db){
        parent::__construct($db);
    }

    public function findCreditsByLogin(string $login): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, pass_hash, is_blocked, is_verified, username
            FROM users
            WHERE email = :email OR username = :username
            LIMIT 1'
        );
        $stmt->execute([':email' => $login, ':username' => $login]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
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
}