<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Publications\PublicationsRepository;
use App\Extensions\ScriptBuilders\Publications\ArticlesScriptDirector;

use PDO;
use App\Lib\Data\Database;

use App\Models\Publications\Publication;
use App\Models\Publications\Article;
use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\ReadingStatus;
use App\Models\Enums\ArticleType;
use App\Models\Filters\UserByPublicationFilters;

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
        ?ArticleType $type = null,
        ?UserByPublicationFilters $userByFilters = null
    ) : array
    {
        $this->director->startTempFilter()->addPublicationSelectTemp();
        if (!empty($search))
            $this->director->addTitleTempFilter($search);

        if ($genreId !== null)
            $this->director->addGenreTempFilter($genreId);

        if ($creatorId !== null)
            $this->director->addCreatorTempFilter($creatorId);

        if (!empty($doi))
            $this->director->addDoiTempFilter($doi);

        if ($userByFilters !== null){
            $filterUserId = $userByFilters->getUserId();
            $this->director->addReadingStatusTempFilter(
                $userByFilters->getReadingStatus(),
                $filterUserId
            );

            if ($userByFilters->getSavedOnly())
                 $this->director->addSavedOnlyTempFilter($filterUserId);
        }

        $this->director->addIncludesTemp($includeObjects);
        $this->director->addOrderTemp($sortBy)->setExtraPaginationTemp($page, $pageSize);

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
        $this->director->startTempFilter()->addBookSelectTemp();
        $this->director->addIncludesTemp($includeObjects);
        $this->director->addConcreteTempFilter($articleid)->setLimitTemp(1);

        $data = $this->director->buildTempFilter();
        $stmt = $this->executeScript($data);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : Article::fromRow($row);
    }
}