<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\UnitOfWork;

final class DatabaseUnitOfWork implements UnitOfWork
{
    public function __construct(private readonly Database $db){}

    public function transactional(callable $fn) : mixed
    {
        $pdo = $this->db->pdo();

        // Защита от случайной вложенности: savepoint, если уже в транзакции.
        $nested = $pdo->inTransaction();

        if ($nested) {
            $savepoint = 'sp_' . bin2hex(random_bytes(4));
            $pdo->exec("SAVEPOINT $savepoint");
        } else {
            $pdo->beginTransaction();
        }

        try {
            $result = $fn($pdo);

            if ($nested) {
                $pdo->exec("RELEASE SAVEPOINT $savepoint");
            } else {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if ($nested) {
                $pdo->exec("ROLLBACK TO SAVEPOINT $savepoint");
            } elseif ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}