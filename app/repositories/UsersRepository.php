<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Repository;
use App\Lib\Database;

final class UsersRepository extends Repository
{
    public function __construct(Database $db){
        parent::__construct($db);
    }

    public function findCreditsByEmail(string $email): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, pass_hash, is_active, username
            FROM users
            WHERE email = :email
            LIMIT 1'
        );
        $stmt->execute([':email' => $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function usernameExists(string $username): bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT 1 FROM users WHERE username = :username LIMIT 1'
        );
        $stmt->execute([':username' => $email]);

        return (bool)$stmt->fetchColumn();
    }

    public function emailExists(string $email) : bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT 1 FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);

        return (bool)$stmt->fetchColumn();
    }

    public function exists(int $id) : bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return (bool)$stmt->fetchColumn();
    }

    public function create($username, $email, $passHash) : ?int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (username, email, pass_hash, is_active)
             VALUES (:username, :email, :passHash, true)
             RETURNING id'
        );

        $stmt->execute([':username' => $username, ':email' => $email, ':passHash' => $passHash]);

        $id = (int)$stmt->fetchColumn();
        return $id === false ? null : $id;
    }
}