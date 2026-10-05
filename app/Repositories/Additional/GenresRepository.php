<?php
declare(strict_types=1);

namespace App\Repositories\Additional;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\Additional\GenresScriptDirector;

use PDO;
use App\Lib\Data\Database;

use App\Models\BasicModel;
use App\Models\Enums\BasicModelSortBy;

final class GenresRepository extends Repository
{
    private readonly GenresScriptDirector $director;

    public function __construct(
        Database $db
    ){
        parent::__construct($db);
        $this->director = new GenresScriptDirector();
    }

    public function getList(
        int $page,
        int $pageSize,
        BasicModelSortBy $sortBy,
        ?string $search = NULL,
    ) : array
    {
        $this->director->startTempFilter()->addGenreSelectTemp();

        if (!empty($search))
            $this->director->addSearchTempFilter($search);

        $this->director->addOrderTemp($sortBy)
            ->setExtraPaginationTemp($page, $pageSize);

        $data = $this->director->buildTempFilter();
        $stmt = $this->executeScript($data);
        
        $items = array_map(
            static fn(array $row) => BasicModel::fromRow($row),
            $stmt->fetchAll(),
        );

        return $items;
    }
}