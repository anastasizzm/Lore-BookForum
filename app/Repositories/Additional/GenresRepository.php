<?php
declare(strict_types=1);

namespace App\Repositories\Additional;

use App\Repositories\Repository;

use PDO;
use App\Lib\Data\Database;

use App\Models\BasicModel;

final class GenresRepository extends Repository
{
    public function __construct(
        Database $db
    ){
        parent::__construct($db);
    }

    public function getList(
        int $page,
        int $pageSize,
        string $search,
    ) : array
    {
        $whereClauses = [];
        $params = [];
        if (!empty($search)){
            $where = 'g.title ILIKE :q';
            $params[':q'] = '%' . $search . '%';
        }
        
        $where = implode(" AND\n", $whereClauses);
        if (!empty($where)) $where = 'WHERE ' . $where;
        
        $sql = "
            SELECT g.id, g.title
            FROM genres g
            $where
            ORDER BY g.title
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo()->prepare($sql);
        foreach($params as $key => $value){
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize + 1, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (($page - 1) * $pageSize), PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(
            static fn(array $row) => BasicModel::fromRow($row),
            $stmt->fetchAll(),
        );

        return $items;
    }
}