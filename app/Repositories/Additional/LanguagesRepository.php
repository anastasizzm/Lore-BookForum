<?php
declare(strict_types=1);

namespace App\Repositories\Additional;

use App\Repositories\Repository;
use App\Lib\Data\Database;
use App\Models\Additional\Language;
use PDO;

final class LanguagesRepository extends Repository
{
    public function __construct(
        Database $db
    ){
        parent::__construct($db);
    }

    public function getList(
        int $page,
        int $pageSize,
    ) : array
    {
        $stmt = $this->pdo()->prepare('SELECT code, created_at, deleted_at FROM languages LIMIT :limit OFFSET :offset');
        $stmt->execute([':limit' => $page+1, ':offset' => ($page-1) * $pageSize]);

        $items = array_map(
            static fn(array $row) => Language::fromRow($row),
            $stmt->fetchAll(),
        );

        return $items;
    }

    public function retrieve(string $code) : ?Language
    {
        $stmt = $this->pdo()->prepare('SELECT code, created_at, deleted_at FROM languages WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? NULL : Language::fromRow($row);
    }

    public function create(string $code) : string 
    {
        $stmt = $this->pdo()->prepare('INSERT INTO languages (code) VALUES (:code) RETURNING code');
        $stmt->execute([':code' => strtolower($code)]);

        return (string)$stmt->fetchColumn();
    }

    public function update(string $code, string $newCode) : bool
    {
        $stmt = $this->pdo()->prepare('UPDATE languages SET code = :newCode WHERE code = :code');
        $stmt->execute([':code' => $code, ':newCode' => strtolower($newCode)]);
        return $stmt->rowCount() > 0;
    }

    public function delete(string $code) : bool 
    {
        $stmt = $this->pdo()->prepare('UPDATE languages SET deleted_at = NOW() WHERE code = :code');
        $stmt->execute([':code' => $code]);
        return $stmt->rowCount() > 0;
    }
}