<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Repository;
use App\Lib\Data\Database;
use App\Models\UserContext\PublicationContext;

final class PublicationsContextRepository extends Repository
{
    public function __construct(
        Database $db
    ) {
        parent::__construct($db);
    }

    /**
     * @param int[] $publicationIds
     * @return array<int, PublicationContext>
     */
    public function loadMap(int $viewerId, array $publicationIds): array
    {
        if ($publicationIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($publicationIds), '?'));

        $sql = "
            WITH viewer AS ( SELECT ?::bigint as user_id )

            SELECT
                p.id AS publication_id,
                COALESCE(bool_or(saved.publication_id IS NOT NULL), false) AS is_saved,
                COALESCE(bool_or(read.publication_id IS NOT NULL), false) AS is_reading,
                COALESCE(bool_or(read.publication_id IS NOT NULL AND read.is_finished), false) AS is_read_completed,
                COALESCE(MAX(r.rating), 0) AS rating
            FROM publications p
            CROSS JOIN viewer v
            LEFT JOIN saved_publications saved ON saved.publication_id = p.id AND saved.user_id = v.user_id
            LEFT JOIN users_read read ON read.publication_id = p.id AND read.user_id = v.user_id
            LEFT JOIN ratings r ON r.publication_id = p.id AND r.user_id = v.user_id
            WHERE p.id IN ($placeholders)
            GROUP BY p.id, v.user_id
        ";

        $params = array_merge([$viewerId], $publicationIds);
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['publication_id']] = PublicationContext::fromRow($row);
        }

        return $map;
    }
}