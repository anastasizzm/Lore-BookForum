<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Repository;

final class ProfilesRepository extends Repository
{
    public function __construct(Database $db){
        parent::__construct($db);
    }

    public function create(int $userId, string $name, string $surname, string $bio) : ?int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO profiles (userId, name, surname, bio)
            VALUES (:userId, :name, :surname, :bio)'
        );

        $stmt->execute([':userId' => $userId, ':name' => $name, ':surname' => $surname, ':bio' => $bio]);
        $id = (int)$stmt->fetchColumn();
        return $id === false ? null : $id;
    }
}