<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Repositories\Publications\PublicationsRepository;
use App\Extensions\ScriptBuilders\Publications\ArticlesScriptDirector;
use App\Extensions\ScriptBuilders\ScriptBuilder;

use PDO;
use App\Lib\Data\Database;
use App\Lib\I18n\Translator;

use App\Models\Publications\Publication;
use App\Models\Publications\Article;
use App\Models\Publications\ArticleModel;
use App\Models\Publications\ArticlePatchModel;
use App\Models\Criterias\Publications\ArticlesCriteria;
use App\Models\Filters\Publications\PublicationsFilters;
use App\Models\Filters\Publications\ArticlesFilters;
use App\Services\Configuration\UnitOfWork;
use App\Models\Scripts\ScriptParam;

/**
 * @property ArticlesScriptDirector $director
 */
final class ArticlesRepository extends PublicationsRepository
{
    public function __construct(
        private readonly UnitOfWork $uow,
        Database $db,
        Translator $translator
    ){
        parent::__construct($db, $translator, new ArticlesScriptDirector());
    }

    protected function getCheckTable() : string { return "articles"; }

    public function getList(ArticlesCriteria $criteria, array $includeObjects = []) : array
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
        $filterCteName = "filtered_articles";

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

    public function retrieve(int $articleId, array $includeObjects) : ?Article
    {
        $this->director->startTempFilter()
            ->addConcreteTempFilter($articleId)->setLimitTemp(1);
        $this->addSelect(Article::class);

        $filterBuilder = $this->director->getTempBuilder();
        $filterCteName = "filtered_article";

        $builder = ScriptBuilder::withTable($filterCteName);
        $builder->addSelect("$filterCteName.*")
            ->addWithBuilder($filterCteName, $filterBuilder, true);
        $this->addIncludes($builder, $filterCteName, $includeObjects);

        $stmt = $this->execute($builder->build(), $builder->getParams());
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : Article::fromRow($row);
    }

    public function validatePublish(int $articleId) : array { return []; }

    public function create(ArticleModel $model, int $creatorId) : int 
    {
        $this->uow->transactional(function () use ($model, $creatorId)
        {
            $pubId = $this->insertPublication(
                $model->title,
                $model->description,
                $model->authorNotes,
                $model->genreId,
                $creatorId,
                $model->iconId
            );
            
            $bookData = $model->content->getBookData();
            $contentData = $model->content->content;
            $this->insertArticle(
                $pubId,
                $model->typeId,
                $bookData?->bookId,
                $bookData?->pageStart,
                $bookData?->pageEnd,
                $contentData->content,
                $model->doi
            );

            return $pubId;
        });
    }

    public function update(
        int $articleId,
        ArticlePatchModel $model
    ) : bool 
    {
        try{
            $this->uow->transactional(function () use ($model, $articleId) : void {
                $exists = $this->updatePublication(
                    $articleId,
                    $model->title,
                    $model->description,
                    $model->authorNotes,
                    $model->genreId,
                    $model->iconId,
                );
                if (!$exists) throw new RuntimeException("Not found");
                $exists = $this->updateBook(
                    $articleId,
                    $model->typeId,
                    $model->doi
                );
                if (!$exists) throw new RuntimeException("Not found");
            });
            return true;
        }
        catch(\RuntimeException $e){
            return false;
        }
    }

    // --- Internals

    protected function applyFilters(PublicationsFilters $filters) : void 
    {
        parent::applyFilters($filters);

        if (!$filters instanceof ArticlesFilters) 
            throw new InvalidArgumentException("Expected instance of ArticlesFilters, got " . get_class($instance));

        if ($filters->typeId !== null)
            $this->director->addTypeTempFilter($filters->typeId);

        if ($filters->bookId !== null)
            $this->director->addBookTempFilter($filters->bookId);

        if (!empty($filters->doi))
            $this->director->addDoiTempFilter($filters->doi);
    }

    protected function handleCustomInclude(ScriptBuilder $builder, string $dataCteName, string $propName) : void
    {
        switch($propName) 
        {
            case "type":
                $includeBuilder = $this->createTypeIncludeBuilder($dataCteName);
                $builder->addWithBuilder("type_include", $includeBuilder)
                    ->addJoin("LEFT JOIN type_include ON type_include.t_id = $dataCteName.type_id")    
                    ->addSelect("type_include.*");
                break;

            case "book":
                $includeBuilder = $this->createBookIncludeBuilder($dataCteName);
                $builder->addWithBuilder("book_include", $includeBuilder)
                    ->addJoin("LEFT JOIN book_include ON book_include.pub_id = $dataCteName.book_id")    
                    ->addSelect("book_include.*");
                break;
        }
    }

    private function createBookIncludeBuilder(string $dataCteName) : ScriptBuilder 
    {
        $builder = ScriptBuilder::withTable("publications", "book");
        $builder->addSelect("book.id as pub_id,\nbook.title as pub_title,\nbook.icon_id as pub_icon_id,\nbook.created_at as pub_created_at");

        return $builder;
    }

    private function createTypeIncludeBuilder(string $dataCteName) : ScriptBuilder 
    {
        $builder = ScriptBuilder::withTable("types");
        $builder->addJoin("INNER JOIN types_translations ON types.id = types_translations.type_id")
            ->addSelect("types.id as t_id,\ntypes.created_at as t_created_at")
            ->addSelect("COALESCE(
                    MAX(CASE WHEN types_translations.code = :currentCulture THEN types_translations.title END),
                    MAX(CASE WHEN types_translations.code = :fallbackCulture THEN types_translations.title END)
                ) as t_title", [':currentCulture' => ScriptParam::asStr($this->translator->locale()), ':fallbackCulture' => ScriptParam::asStr($this->translator->fallback())])
            ->addWhere("types.id IN (SELECT type_id FROM $dataCteName)")
            ->setGroup("types.id");

        return $builder;
    }

    protected function addCustomSelect(string $className) : void
    {
        switch($className)
        {
            case App\Models\Publications\Article::class:
                $this->director->addArticleSelectTemp();
                break;
            default:
                throw new \RuntimeException("Invalid class provided");
                break;
        }
    }

    protected function insertArticle(
        int $publicationId,
        int $typeId,
        ?int $bookId,
        ?int $pageStart,
        ?int $pageEnd,
        ?string $content,
        ?string $doi = NULL
    ) : int
    {
        $values = [
            ':pubId' => ScriptParam::asInt($publicationId),
            ':typId' => ScriptParam::asInt($typeId),
            ':content' => ScriptParam::asStr($content),
            ':bookId' => ScriptParam::asInt($bookId),
            ':pStart' => ScriptParam::asInt($pageStart),
            ':pEnd' => ScriptParam::asInt($pageEnd),
            ':doi' => ScriptParam::asStr($doi?->toString())
        ];

        $sql = 'INSERT INTO articles (publication_id, type_id, content, book_id, page_start, page_end, doi)
                VALUES (:pubId, :typId, :content, :bookId, :pStart, :pEnd, :doi) RETURNING publication_id';
        
        $stmt = $this->execute($sql, $values);
        return (int)$stmt->fetchColumn();
    }

    protected function updateBook(
        int $articleId,
        int|false $typeId,
        string|null|false $doi = NULL
    ) : bool 
    {
        $sets = [];
        $params = [':id' => ScriptParam::asInt($articleId)];

        if ($typeId !== false) {
            $sets[] = 'type_id = :typeId';
            $params[':typeId'] = ScriptParam::asInt($typeId);
        }

        if ($doi !== false) {
            $sets[] = 'doi = :doi';
            $params[':doi'] = ScriptParam::asStr($doi);
        }

        if (empty($sets)) {
            return true; 
        }

        $sql = "UPDATE articles 
                SET " . implode(', ', $sets) . " 
                WHERE publication_id = :id 
                RETURNING publication_id";

        $stmt = $this->execute($sql, $params);
        return $stmt->fetchColumn() !== false;
    }
}