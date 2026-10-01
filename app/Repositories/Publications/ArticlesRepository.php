<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Publications\PublicationsRepository;

use PDO;
use App\Lib\Data\Database;

use App\Models\Publications\Publication;
use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ArticleType;

final class ArticlesRepository extends PublicationsRepository
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
        PublicationsSortBy $sortBy,
        array $includeObjects,
        ?int $genreId = null,
        ?int $creatorId = null,
        ?int $bookId = null,
        ?string $doi = null,
        ?ArticleType $type = null
    ) : array
    {
        $whereClauses = [];
        $joinClauses = [];
        $selectClauses = [];
        $params = [];
        if (!empty($search)){
            $whereClauses[] = 'p.title ILIKE :q';
            $params[':q'] = '%' . $search . '%';
        }
        if ($genreId !== null){
            $whereClauses[] = 'p.genre_id = :genreId';
            $params[':genreId'] = $genreId;
        }

        if ($creatorId !== null){
            $whereClauses[] = 'p.creator_id = :creatorId';
            $params[':creatorId'] = $creatorId;
        }

        if ($bookId !== null){
            $whereClauses[] = 'a.book_id = :bookId';
            $params[':bookId'] = $bookId;
        }

        if (!empty($doi)){
            $whereClauses[] = 'a.doi ILIKE :doi';
            $params[':doi'] = $doi . '%';
        }

        if ($type !== null){
            $whereClauses[] = 'a.type = :type';
            $params[':type'] = $type->value;
        }

        foreach($includeObjects as $prop){
            switch($prop){
                case 'creator':
                    $joinClauses[] = 'INNER JOIN users u ON u.id = p.creator_id';
                    $joinClauses[] = 'INNER JOIN profiles prof ON prof.user_id = u.id';
                    $selectClauses[] = "u.id as u_id,\nu.username as u_username,\nprof.name as u_name,\nprof.surname as u_surname,\nprof.avatar as u_avatar";
                    break;
                case 'genre':
                    $joinClauses[] = 'INNER JOIN genres g ON g.id = p.genre_id';
                    $selectClauses[] = "g.id as g_id,\ng.title as g_title";
                    break;
            }
        }

        $where = implode(" AND\n", $whereClauses);
        if (!empty($where)) $where = 'WHERE ' . $where;

        $joins = implode("\n", $joinClauses);
        $select = "SELECT p.id,\np.title,\np.creator_id,\np.icon_id,\np.created_at,\np.rating_avg,\np.comments_count,\np.genre_id";
        if (!empty($selectClauses))
            $select = $select . ",\n" . implode(",\n", $selectClauses);

        $order = match($sortBy){
            PublicationsSortBy::Popularity => 'ORDER BY p.rating_avg DESC',
            PublicationsSortBy::Newest => 'ORDER BY p.created_at DESC',
            PublicationsSortBy::Alphabet => 'ORDER BY p.title'
        };
        
        $sql = "
            $select
            FROM publications p
            INNER JOIN articles a ON a.publication_id = p.id
            $joins
            $where
            $order
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
            static fn(array $row) => Publication::fromRow($row),
            $stmt->fetchAll(),
        );

        return $items;
    }
}