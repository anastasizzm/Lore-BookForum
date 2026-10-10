<?php
declare(strict_types=1);

namespace App\Repositories\Additional;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\Additional\CategoriesScriptDirector;

use PDO;
use App\Lib\Data\Database;
use App\Lib\I18n\Translator;

use App\Models\BasicModel;
use App\Models\Enums\BasicModelSortBy;

final class CategoriesRepository extends Repository
{
    private readonly CategoriesScriptDirector $director;

    public function __construct(
        private readonly Translator $translator,
        Database $db
    ){
        parent::__construct($db);
        $this->director = new CategoriesScriptDirector();
    }

    public function getList(
        int $page,
        int $pageSize,
        BasicModelSortBy $sortBy,
        ?string $search = NULL,
    ) : array
    {
        $this->director->startTempFilter()->addCategorySelectTemp($this->translator->locale(), $this->translator->fallback());

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