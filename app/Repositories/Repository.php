<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Lib\Data\Database;
use PDO;

abstract class Repository
{
    public function __construct(protected readonly Database $db){}

    protected function pdo() : PDO
    {
        return $this->db->pdo();
    }
}