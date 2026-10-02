<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Repository;

use App\Lib\Data\Database;

abstract class PublicationsRepository extends Repository
{
    public abstract function checkType(int $publicationId) : bool;

    public function __construct(
        Database $db
    ){
        parent::__construct($db);
    }

    public function save(int $userId, int $publicationId) : void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO saved_publications (user_id, publication_id)
            VALUES (:userId, :publicationId)'
        );

        $stmt->execute([':userId' => $userId, ':publicationId' => $publicationId]);
    }

    public function deleteSave(int $userId, int $publicationId) : void
    {
        $stmt = $this->pdo()->prepare(
            'DELETE FROM saved_publications WHERE user_id = :userId AND publication_id = :publicationId'
        );
        $stmt->execute([':userId' => $userId, ':publicationId' => $publicationId]);
    }

    protected function addIncludeObjects(array &$selectClauses, array &$joinClauses, array $includeObjects)
    {
        foreach($includeObjects as $prop)
        {
            switch(lower($prop)){
                case 'creator':
                    $joinClauses[] = 'INNER JOIN users ON users.id = p.creator_id';
                    $joinClauses[] = 'INNER JOIN profiles ON profiles.user_id = u.id';
                    $selectClauses[] = "users.id as u_id,\nusers.username as u_username,\nprofiles.name as u_name,\nprofiles.surname as u_surname,\nprofiles.avatar as u_avatar";
                    break;
                case 'genre':
                    $joinClauses[] = 'INNER JOIN genres ON genres.id = publications.genre_id';
                    $selectClauses[] = "genres.id as g_id,\ngenres.title as g_title";
                    break;
            }
        }
    }

    
}