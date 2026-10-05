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
}