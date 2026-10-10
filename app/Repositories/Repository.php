<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Lib\Data\Database;
use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;
use PDO;
use PDOStatement;

abstract class Repository
{
    public function __construct(protected readonly Database $db){}

    protected function pdo() : PDO
    {
        return $this->db->pdo();
    }

    protected function executeScript(ScriptData $data) : PDOStatement
    {
        return $this->execute($data->getScript(), $data->getParams());
    }

    protected function execute(string $script, array $params) : PDOStatement
    {
        $stmt = $this->pdo()->prepare($script);
        foreach($params as $key => $scriptParam)
        {
            if ($scriptParam instanceof ScriptParam)
               $stmt->bindValue($key, $scriptParam->getValue(), $scriptParam->getType());
            else $stmt->bindValue($key, $scriptParam);
        }

        $stmt->execute();
        return $stmt;
    }
}