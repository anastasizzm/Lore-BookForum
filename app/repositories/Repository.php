<?php
declare(strict_types=1);

use App\Lib\Database;
use PDO;

abstract class Repository
{
    public function __construct(protected readonly Database $db){}

    protected function pdo() : PDO
    {
        return $this->db->pdo();
    }
}