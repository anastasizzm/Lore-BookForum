<?php
declare(strict_types=1);

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

    public function exists(int $id) : bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return (bool)$stmt->fetchColumn();
    }
}