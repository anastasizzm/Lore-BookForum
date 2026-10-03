<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Publications\PublicationsRepository;
use App\Extensions\ScriptBuilders\Publications\BooksScriptDirector;

use PDO;
use App\Lib\Data\Database;

use App\Models\Publications\Publication;
use App\Models\Publications\Book;
use App\Models\Criterias\Publications\BooksCriteria;

final class BooksRepository extends PublicationsRepository
{
    private readonly BooksScriptDirector $director;

    public function __construct(
        Database $db
    ){
        parent::__construct($db);
        $this->director = new BooksScriptDirector();
    }

    public function checkType(int $publicationId) : bool
    {
        $data = $this->director->getExistsScript($publicationId);
        $stmt = $this->executeScript($data);

        return $stmt->fetchColumn() !== false;
    }

    public function getList(BooksCriteria $criteria, array $includeObjects = []) : array
    {
        $this->director->startTempFilter()->addPublicationSelectTemp();
        $filters = $criteria->filters;
        $userFilters = $criteria->userCriteria;
        
        if (!empty($filters->search))
            $this->director->addTitleTempFilter($filters->search);

        if ($filters->genreId !== null)
            $this->director->addGenreTempFilter($filters->genreId);

        if ($filters->creatorId !== null)
            $this->director->addCreatorTempFilter($filters->creatorId);

        if (!empty($filters->isbn))
            $this->director->addIsbnTempFilter($filters->isbn);

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

    public function retrieve(int $bookId, array $includeObjects) : ?Book
    {
        $this->director->startTempFilter()->addBookSelectTemp();
        $this->director->addIncludesTemp($includeObjects);
        $this->director->addConcreteTempFilter($bookId)->setLimitTemp(1);

        $data = $this->director->buildTempFilter();
        $stmt = $this->executeScript($data);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : Book::fromRow($row);
    }
}