<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Models\Filters\Publications\PublicationsFilters;
use App\Models\Criterias\Publications\UserRelationCriteria;
use App\Models\Enums\PublicationsSortBy;
use App\Models\Enums\PublicationStatus;
use App\Models\Scripts\ScriptParam;
use App\Models\Publications\PublicationShort;
use App\Models\Publications\Publication;
use App\Models\Publications\PublicationExtended;
use App\Models\Uuid;

use App\Extensions\ScriptBuilders\Publications\PublicationsScriptDirector;
use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Repositories\Repository;
use App\Lib\Data\Database;
use App\Lib\I18n\Translator;
use PDO;

abstract class PublicationsRepository extends Repository
{
    private const array ALLOWED_TABLES = ['books', 'articles'];

    public function __construct(
        Database $db,
        protected Translator $translator,
        protected PublicationsScriptDirector $director
    ){
        parent::__construct($db);
    }

    protected abstract function getCheckTable() : string;

    private function checkTable() : string 
    {
        $table = $this->getCheckTable();

        if (!in_array($table, self::ALLOWED_TABLES, true))
            throw new \InvalidArgumentException("'{$table}' is not allowed");

        return $table;
    }

    public function retrieveShort(int $publicationId) : ?PublicationShort 
    {
        $this->director->startTempFilter()->addPublicationSelectTemp()
            ->addConcreteTempFilter($publicationId)->setLimitTemp(1);

        $filterBuilder = $this->director->getTempBuilder();
        $filterCteName = "filtered_pub";

        $builder = ScriptBuilder::withTable($filterCteName);
        $builder->addSelect("$filterCteName.*")
            ->addWithBuilder($filterCteName, $filterBuilder, true);

        $stmt = $this->execute($builder->build(), $builder->getParams());
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : PublicationShort::fromRow($row);
    }

    public function rate(int $userId, int $publicationId, int $rating) : void 
    {
        $checkTable = $this->checkTable();

        $sql = "INSERT INTO ratings (user_id, publication_id, rating)
                    SELECT :userId::bigint, checker.publication_id, :rating::smallint
                    FROM {$checkTable} checker
                    WHERE checker.publication_id = :pubId
                    ON CONFLICT (user_id, publication_id)
                    DO UPDATE SET rating = EXCLUDED.rating;
                ";
        $this->execute($sql, [
            ':userId' => ScriptParam::asInt($userId), 
            ':pubId' => ScriptParam::asInt($publicationId), 
            ':rating' => ScriptParam::asInt($rating)
        ]);
    }

    public function unrate(int $userId, int $publicationId) : void 
    {
        $checkTable = $this->checkTable();

        $sql = "DELETE FROM ratings 
                WHERE user_id = :userId 
                    AND publication_id = :pubIdOuter
                    AND EXISTS (
                        SELECT 1 FROM {$checkTable} checker
                        WHERE checker.publication_id = :pubIdInner
                        )";

        $this->execute($sql, [
            ':userId' => ScriptParam::asInt($userId), 
            ':pubIdInner' => ScriptParam::asInt($publicationId), 
            ':pubIdOuter' => ScriptParam::asInt($publicationId), 
        ]);
    }

    public function save(int $userId, int $publicationId) : void
    {
        $checkTable = $this->checkTable();

        $sql = "INSERT INTO saved_publications (user_id, publication_id)
                SELECT :userId::bigint, checker.publication_id 
                FROM {$checkTable} checker
                WHERE checker.publicaiton_id = :pubId
                ON CONFLICT DO NOTHING";

        $this->execute($sql, [
            ':userId' => ScriptParam::asInt($userId), 
            ':pubId' => ScriptParam::asInt($publicationId), 
        ]);
    }

    public function deleteSave(int $userId, int $publicationId) : void
    {
        $checkTable = $this->checkTable();
    
        $sql = "DELETE FROM saved_publications 
                    WHERE user_id = :userId 
                        AND publication_id = :pubIdOuter
                        AND EXISTS (
                            SELECT 1 FROM {$checkTable} checker
                            WHERE checker.publication_id = :pubIdInner
                        )";
        
        $this->execute($sql, [
            ':userId' => ScriptParam::asInt($userId), 
            ':pubIdInner' => ScriptParam::asInt($publicationId), 
            ':pubIdOuter' => ScriptParam::asInt($publicationId), 
        ]);
    }  

    /** @return array<string> */
    public abstract function validatePublish(int $publicationId) : array;

    public function publish(int $publicationId) : bool 
    {
        $checkTable = $this->checkTable();

        $sql = "UPDATE publications
            SET status = 'public'
            WHERE id = :pubIdOuter
            AND status != 'public'
            AND EXISTS (
                    SELECT 1 FROM {$checkTable} checker
                    WHERE checker.publication_id = :pubIdInner
                )";

        $stmt = $this->execute($sql, [
            ':pubIdInner' => ScriptParam::asInt($publicationId), 
            ':pubIdOuter' => ScriptParam::asInt($publicationId), 
        ]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $publicationId) : bool 
    {
        $checkTable = $this->checkTable();

        $sql = "UPDATE publications
            SET deleted_at = NOW()
            WHERE id = :pubIdOuter
            AND EXISTS (
                    SELECT 1 FROM {$checkTable} checker
                    WHERE checker.publication_id = :pubIdInner
                )";

        $stmt = $this->execute($sql, [
            ':pubIdInner' => ScriptParam::asInt($publicationId), 
            ':pubIdOuter' => ScriptParam::asInt($publicationId), 
        ]);
        return $stmt->rowCount() > 0;
    }

    // --- Internals

    protected function insertPublication(
        string $title, 
        string $description, 
        string $authorNotes,
        int $genreId,
        int $creatorId,
        ?Uuid $iconId,
        PublicationStatus $status = PublicationStatus::Draft,
    ) : int
    {
        $values = [
            ':t' => ScriptParam::asStr($title),
            ':d' => ScriptParam::asStr($description),
            ':n' => ScriptParam::asStr($authorNotes),
            ':g' => ScriptParam::asInt($genreId),
            ':c' => ScriptParam::asInt($creatorId),
            ':o' => ScriptParam::asInt($creatorId),
            ':i' => ScriptParam::asStr($iconId?->toString()),
            ':st' => ScriptParam::asStr($status->value)
        ];

        $sql = 'INSERT INTO publications (title, description, author_notes, genre_id, creator_id, owner_id, icon_id, status)
                VALUES (:t, :d, :n, :g, :c, :o, :i, :st) RETURNING id';
        
        $stmt = $this->execute($sql, $values);
        return (int)$stmt->fetchColumn();
    }

    protected function updatePublication(
        int $publicationId,
        string|false $title,
        string|false $description,
        string|false $authorNotes,
        int|false $genreId,
        Uuid|null|false $iconId
    ) : bool
    {
        $sets = [];
        $params = [':id' => ScriptParam::asInt($publicationId)];

        // 1. Build the dynamic SET clause
        if ($title !== false) {
            $sets[] = 'title = :title';
            $params[':title'] = ScriptParam::asStr($title);
        }

        if ($description !== false) {
            $sets[] = 'description = :description';
            $params[':description'] = ScriptParam::asStr($description);
        }

        if ($authorNotes !== false) {
            $sets[] = 'author_notes = :authorNotes';
            $params[':authorNotes'] = ScriptParam::asStr($authorNotes);
        }

        if ($genreId !== false) {
            $sets[] = 'genre_id = :genreId';
            $params[':genreId'] = ScriptParam::asInt($genreId);
        }

        if ($iconId !== false) {
            $sets[] = 'icon_id = :iconId';
            $params[':iconId'] = ScriptParam::asStr($iconId?->toString());
        }

        if (empty($sets)) {
            return true; 
        }

        $sql = "UPDATE publications 
                SET " . implode(', ', $sets) . " 
                WHERE id = :id 
                RETURNING id";

        $stmt = $this->execute($sql, $params);
        return $stmt->fetchColumn() !== false;
    }

    protected function addSelect(string $className) : void
    {
        switch($className)
        {
            case PublicationShort::class:
                $this->director->addPublicationShortSelectTemp();
                break;
            case Publication::class:
                $this->director->addPublicationSelectTemp();
                break;
            case PublicationExtended::class:
                $this->director->addPublicationExtendedSelectTemp();
                break;
            default:
                $this->addCustomSelect($className);
                break;
        }
    }
    protected abstract function addCustomSelect(string $className) : void;

    protected function applyFilters(PublicationsFilters $filters) : void 
    {
        if (!empty($filters->search))
            $this->director->addSearchTempFilter($filters->search);

        if ($filters->genreId !== null)
            $this->director->addGenreTempFilter($filters->genreId);

        if ($filters->creatorId !== null)
            $this->director->addCreatorTempFilter($filters->creatorId);

        $this->director->addStatusTempFilter($filters->status);
    }

    protected function applyUserRelationCriteria(UserRelationCriteria $criteria) : void 
    {
        $this->director->addReadingStatusTempFilter(
            $criteria->status,
            $criteria->viewerId
        );

        if ($criteria->savedOnly)
            $this->director->addSavedOnlyTempFilter($criteria->viewerId);
    }

    protected function addOrder(ScriptBuilder $builder, string $dataCteName, PublicationsSortBy $sort) : void 
    {
        $order = match($sort){
            PublicationsSortBy::Popularity => "$dataCteName.rating_avg DESC",
            PublicationsSortBy::Newest => "$dataCteName.created_at DESC",
            PublicationsSortBy::Alphabet => "$dataCteName.title"
        };
        $builder->setOrder($order);
    }

    protected function addIncludes(ScriptBuilder $builder, string $dataCteName, array $includeObjects) : void
    {
        foreach($includeObjects as $prop)
        {
            $prop = strtolower($prop);
            switch($prop)
            {
                case 'creator':
                    $includeBuilder = $this->createCreatorIncludeBuilder($dataCteName);
                    $builder->addWithBuilder("creator_include", $includeBuilder)
                        ->addJoin("LEFT JOIN creator_include ON creator_include.u_id = $dataCteName.creator_id")    
                        ->addSelect("creator_include.*");
                    break;

                case 'genre':
                    $includeBuilder = $this->createGenreIncludeBuilder($dataCteName);
                    $builder->addWithBuilder("genre_include", $includeBuilder)
                        ->addJoin("LEFT JOIN genre_include ON genre_include.g_id = $dataCteName.genre_id")    
                        ->addSelect("genre_include.*");
                    break;

                default:
                    $this->handleCustomInclude($builder, $dataCteName, $prop);
                    break;
            }
        }
    }

    private function createCreatorIncludeBuilder(string $dataCteName) : ScriptBuilder
    {
        $builder = ScriptBuilder::withTable("users");
        $builder->addJoin("INNER JOIN profiles ON profiles.user_id = users.id")
            ->addWhere("users.id IN (SELECT creator_id FROM $dataCteName)")
            ->addSelect("users.id as u_id,\nusers.username as u_username")
            ->addSelect("profiles.name as u_name,\nprofiles.surname as u_surname,\nprofiles.avatar as u_avatar");

        return $builder;
    }

    private function createGenreIncludeBuilder(string $dataCteName) : ScriptBuilder 
    {
        $builder = ScriptBuilder::withTable("genres");
        $builder->addJoin("INNER JOIN genres_translations ON genres.id = genres_translations.genre_id")
            ->addSelect("genres.id as g_id,\ngenres.created_at as g_created_at")
            ->addSelect("COALESCE(
                    MAX(CASE WHEN genres_translations.code = :currentCulture THEN genres_translations.title END),
                    MAX(CASE WHEN genres_translations.code = :fallbackCulture THEN genres_translations.title END)
                ) as g_title", [':currentCulture' => ScriptParam::asStr($this->translator->locale()), ':fallbackCulture' => ScriptParam::asStr($this->translator->fallback())])
            ->addWhere("genres.id IN (SELECT genre_id FROM $dataCteName)")
            ->setGroup("genres.id");

        return $builder;
    }

    protected abstract function handleCustomInclude(ScriptBuilder $builder, string $dataCteName, string $propName) : void;
}