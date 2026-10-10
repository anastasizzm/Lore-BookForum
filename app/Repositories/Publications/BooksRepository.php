<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Publications\PublicationsRepository;
use App\Extensions\ScriptBuilders\Publications\BooksScriptDirector;
use App\Extensions\ScriptBuilders\ScriptBuilder;

use PDO;
use App\Lib\Data\Database;
use App\Lib\I18n\Translator;

use App\Models\Publications\Publication;
use App\Models\Publications\Book;
use App\Models\Criterias\Publications\BooksCriteria;
use App\Models\Filters\Publications\PublicationsFilters;
use App\Models\Filters\Publications\BooksFilters;
use App\Models\Scripts\ScriptParam;

use InvalidArgumentException;

/**
 * @property BooksScriptDirector $director
 */
final class BooksRepository extends PublicationsRepository
{
    public function __construct(
        Database $db,
        Translator $translator
    ){
        parent::__construct($db, $translator, new BooksScriptDirector());
    }

    public function checkType(int $publicationId) : bool
    {
        $data = $this->director->getExistsScript($publicationId);
        $stmt = $this->executeScript($data);

        return $stmt->fetchColumn() !== false;
    }

    protected function applyFilters(PublicationsFilters $filters) : void 
    {
        parent::applyFilters($filters);

        if (!$filters instanceof BooksFilters) 
            throw new InvalidArgumentException("Expected instance of BooksFilters, got " . get_class($instance));

        if ($filters->categoryId !== null)
            $this->director->addCategoryTempFilter($filters->categoryId);

        if (!empty($filters->isbn))
            $this->director->addIsbnTempFilter($filters->isbn);
    }

    protected function handleCustomInclude(ScriptBuilder $builder, string $dataCteName, string $propName) : void
    {
        switch($propName) 
        {
            case "category":
                $includeBuilder = $this->createCategoryIncludeBuilder($dataCteName);
                $builder->addWithBuilder("category_include", $includeBuilder)
                    ->addJoin("LEFT JOIN category_include ON category_include.c_id = $dataCteName.category_id")    
                    ->addSelect("category_include.*");
                break;
        }
    }

    private function createCategoryIncludeBuilder(string $dataCteName) : ScriptBuilder 
    {
        $builder = ScriptBuilder::withTable("categories");
        $builder->addJoin("INNER JOIN categories_translations ON categories.id = categories_translations.category_id")
            ->addSelect("categories.id as c_id,\ncategories.created_at as c_created_at")
            ->addSelect("COALESCE(
                    MAX(CASE WHEN categories_translations.code = :currentCulture THEN categories_translations.title END),
                    MAX(CASE WHEN categories_translations.code = :fallbackCulture THEN categories_translations.title END)
                ) as c_title", [':currentCulture' => ScriptParam::asStr($this->translator->locale()), ':fallbackCulture' => ScriptParam::asStr($this->translator->fallback())])
            ->addWhere("categories.id IN (SELECT category_id FROM $dataCteName)")
            ->setGroup("categories.id");

        return $builder;
    }

    public function getList(BooksCriteria $criteria, array $includeObjects = []) : array
    {
        $this->director->startTempFilter()->addPublicationSelectTemp();
        
        $filters = $criteria->filters;
        $this->applyFilters($filters);
        
        $userFilters = $criteria->userCriteria;
        if ($userFilters !== null){
            $this->applyUserRelationFilters($userFilters);
        }

        $this->director->addOrderTemp($criteria->sortBy)
            ->setExtraPaginationTemp($criteria->page, $criteria->pageSize);

        $filterBuilder = $this->director->getTempBuilder();
        $filterCteName = "filtered_books";

        $builder = ScriptBuilder::withTable($filterCteName);
        $builder->addSelect("$filterCteName.*")
            ->addWithBuilder($filterCteName, $filterBuilder, true);
        $this->addIncludes($builder, $filterCteName, $includeObjects);
        $this->addOrder($builder, $filterCteName, $criteria->sortBy);

        $stmt = $this->execute($builder->build(), $builder->getParams());
        $items = array_map(
            static fn(array $row) => Publication::fromRow($row),
            $stmt->fetchAll(),
        );

        return $items;
    }

    public function retrieve(int $bookId, array $includeObjects) : ?Book
    {
        $this->director->startTempFilter()->addBookSelectTemp()
            ->addConcreteTempFilter($bookId)->setLimitTemp(1);

        $filterBuilder = $this->director->getTempBuilder();
        $filterCteName = "filtered_book";

        $builder = ScriptBuilder::withTable($filterCteName);
        $builder->addSelect("$filterCteName.*")
            ->addWithBuilder($filterCteName, $filterBuilder, true);
        $this->addIncludes($builder, $filterCteName, $includeObjects);

        $stmt = $this->execute($builder->build(), $builder->getParams());
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : Book::fromRow($row);
    }
}