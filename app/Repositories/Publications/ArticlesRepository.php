<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Publications\PublicationsRepository;
use App\Extensions\ScriptBuilders\Publications\ArticlesScriptDirector;

use PDO;
use App\Lib\Data\Database;

use App\Models\Publications\Publication;
use App\Models\Publications\Article;
use App\Models\Criterias\Publications\ArticlesCriteria;

final class ArticlesRepository extends PublicationsRepository
{
    private readonly ArticlesScriptDirector $director;

    public function __construct(
        Database $db
    ){
        parent::__construct($db);
        $this->director = new ArticlesScriptDirector();
    }

    public function checkType(int $publicationId) : bool
    {
        $data = $this->director->getExistsScript($publicationId);
        $stmt = $this->executeScript($data);

        return $stmt->fetchColumn() !== false;
    }

    public function getList(ArticlesCriteria $criteria, array $includeObjects = []) : array
    {
        $this->director->startTempFilter()->addPublicationSelectTemp();
        $filters = $criteria->filters;
        $userFilters = $criteria->userCriteria;
        
        if (!empty($filters->search))
            $this->director->addSearchTempFilter($search);

        if ($filters->genreId !== null)
            $this->director->addGenreTempFilter($genreId);

        if ($filters->creatorId !== null)
            $this->director->addCreatorTempFilter($filters->creatorId);

        if (!empty($filters->doi))
            $this->director->addDoiTempFilter($filters->doi);

        if ($userFilters !== null){
            $this->director->addReadingStatusTempFilter(
                $userFilters->status,
                $userFilters->viewerId
            );

            if ($userFilters->savedOnly)
                 $this->director->addSavedOnlyTempFilter($userFilters->viewerId);
        }

        $this->director->addIncludesTemp($includeObjects);
        $this->director->addOrderTemp($criteria->sortBy)
            ->setExtraPaginationTemp($criteria->page, $criteria->pageSize);

        $data = $this->director->buildTempFilter();
        $stmt = $this->executeScript($data);

        $items = array_map(
            static fn(array $row) => Publication::fromRow($row),
            $stmt->fetchAll(),
        );

        return $items;
    }

    public function retrieve(int $articleid, array $includeObjects) : ?Article
    {
        $this->director->startTempFilter()->addArticleSelectTemp();
        $this->director->addIncludesTemp($includeObjects);
        $this->director->addConcreteTempFilter($articleid)->setLimitTemp(1);

        $data = $this->director->buildTempFilter();
        $stmt = $this->executeScript($data);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : Article::fromRow($row);
    }
}