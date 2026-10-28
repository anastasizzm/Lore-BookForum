<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Publications\PublicationsRepository;
use App\Extensions\ScriptBuilders\Publications\BooksScriptDirector;
use App\Extensions\ScriptBuilders\ScriptBuilder;

use PDO;
use App\Lib\Data\Database;
use App\Lib\I18n\Translator;

use App\Models\Uuid;
use App\Models\Publications\Publication;
use App\Models\Publications\Book;
use App\Models\Enums\FileCategory;
use App\Models\Publications\BookModel;
use App\Models\Criterias\Publications\BooksCriteria;
use App\Models\Filters\Publications\PublicationsFilters;
use App\Models\Filters\Publications\BooksFilters;
use App\Models\Scripts\ScriptParam;

use App\Services\Configuration\UnitOfWork;

use InvalidArgumentException;

/**
 * @property BooksScriptDirector $director
 */
final class BooksRepository extends PublicationsRepository
{
    public function __construct(
        private readonly UnitOfWork $uow,
        Database $db,
        Translator $translator
    ){
        parent::__construct($db, $translator, new BooksScriptDirector());
    }

    protected function getCheckTable() : string { return "books"; }

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

    protected function addCustomSelect(string $className) : void
    {
        switch($className)
        {
            case Book::class:
                $this->director->addBookSelectTemp();
                break;
            default:
                throw new \RuntimeException("Invalid class provided");
                break;
        }
    }

    public function getList(BooksCriteria $criteria, array $includeObjects = []) : array
    {
        $this->director->startTempFilter()
            ->addViewerTemp($criteria->viewerId)
            ->addActiveTempFilter();
            
        $this->addSelect($criteria->classType);
        
        $filters = $criteria->filters;
        $this->applyFilters($filters);
        
        $userFilters = $criteria->userCriteria;
        if ($userFilters !== null){
            $this->applyUserRelationCriteria($userFilters);
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
            static fn(array $row) => $criteria->classType::fromRow($row),
            $stmt->fetchAll(),
        );

        return $items;
    }

    public function retrieve(int $bookId, array $includeObjects) : ?Book
    {
        $this->director->startTempFilter()
            ->addConcreteTempFilter($bookId)->setLimitTemp(1);
        $this->addSelect(Book::class);

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

    public function create(
        BookModel $model,
        int $creatorId,
    ) : int 
    {
        return $this->uow->transactional(function () use ($model, $creatorId) : int {
            $pubId = $this->insertPublication(
                $model->title,
                $model->description,
                $model->authorNotes,
                $model->genreId,
                $creatorId,
                $model->iconId
            );
            $this->insertBook(
                $pubId,
                $model->categoryId,
                $model->publisher,
                $model->pages,
                $model->isbn
            );

            return $pubId;
        });
    }

    protected function insertBook(
        int $publicationId,
        int $categoryId,
        string $publisher,
        int $pagesCount,
        ?string $isbn = NULL
    ) : int
    {
        $values = [
            ':pubId' => ScriptParam::asInt($publicationId),
            ':catId' => ScriptParam::asInt($categoryId),
            ':publ' => ScriptParam::asStr($publisher),
            ':pages' => ScriptParam::asInt($pagesCount),
            ':isbn' => ScriptParam::asStr($isbn?->toString())
        ];

        $sql = 'INSERT INTO books (publication_id, category_id, publisher, pages, isbn)
                VALUES (:pubId, :catId, :publ, :pages, :isbn) RETURNING publication_id';
        
        $stmt = $this->execute($sql, $values);
        return (int)$stmt->fetchColumn();
    }

    public function identifyContent(int $bookId, string $format) : ?Uuid 
    {
        $sql = "SELECT content_id FROM books_content 
            WHERE book_id = :bookId AND format = :format";

        $params = [
            ':bookId' => ScriptParam::asInt($bookId),
            ':format' => ScriptParam::asStr($format)
        ];

        $stmt = $this->execute($sql, $params);
        $id = $stmt->fetchColumn();
        return !is_string($id) ? NULL : Uuid::fromString($id);
    }

    public function addContent(int $bookId, Uuid $contentId) : void
    {
        $values = [
            ':bookId' => ScriptParam::asInt($bookId),
            ':contentId' => ScriptParam::asStr($contentId->toString()),
        ];

        $sql = 'INSERT INTO books_content (book_id, content_id, format)
                SELECT :bookId::bigint, id, format FROM files WHERE id = :contentId';

        $this->execute($sql, $values);
    }

    public function addPendingContents(int $bookId, array $contentIds) : void 
    {
        if ($contentIds === []) return;

        $builder = ScriptBuilder::withTable("files");
        $builder->addSelect(":bookId, id, format", [':bookId' => ScriptParam::asInt($bookId)]);

        $contentIdsArray = '{' . implode(',',  array_map(function($id) : string {
            if ($id instanceof Uuid) return $id->toString();
            else return (string)$id;
        }, $contentIds)) . '}';

        $builder->addWith("contents", "SELECT content_id FROM unnest(:contentIds::uuid[]) AS t(content_id)", true, [":contentIds" => $contentIdsArray]);
        $builder->addWhere("id IN (SELECT content_id FROM contents)")->addWhere("category = :category", [":category" => FileCategory::Book->value]);
    
        $sql = "INSERT INTO books_content (book_id, content_id, format)\n" . $builder->build() . "\nRETURNING content_id";
        $stmt = $this->execute($sql, $builder->getParams());

        $pendingFilesIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->activateFiles($pendingFilesIds);
    }

    private function activateFiles(array $fileIds) : void 
    {
        $fileIdsArray = '{' . implode(',', $fileIds) . '}';
        $stmt = $this->pdo()->prepare("UPDATE files SET status = 'active' WHERE id = ANY(:fileIds::uuid[])");
        $stmt->execute([':fileIds' => $fileIdsArray]);
    }

    public function removeContent(int $bookId, Uuid $contentId) : void
    {
        $values = [
            ':bookId' => ScriptParam::asInt($bookId),
            ':contentId' => ScriptParam::asStr($contentId->toString()),
        ];

        $sql = 'DELETE FROM books_content WHERE book_id = :bookId AND content_id = :contentId';
        $stmt = $this->execute($sql, $values);
    }

    public function validatePublish(int $bookId) : bool 
    {
        $sql = "SELECT 1
                FROM books_content bc
                WHERE bc.book_id = :bookId
                    AND LOWER(bc.format) = 'pdf'";

        $stmt = $this->execute($sql, [':bookId' => ScriptParam::asInt($bookId)]);
        return $stmt->rowCount() > 0;
    }
}