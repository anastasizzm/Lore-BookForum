<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Repository;

use App\Lib\Data\Database;

abstract class PublicationsRepository extends Repository
{
    public function __construct(
        Database $db
    ){
        parent::__construct($db);
    }
}