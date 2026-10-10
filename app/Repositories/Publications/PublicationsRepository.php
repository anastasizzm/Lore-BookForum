<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Models\Filters\Publications\PublicationsFilters;
use App\Models\Criterias\Publications\UserRelationCriteria;
use App\Models\Enums\PublicationsSortBy;
use App\Models\Scripts\ScriptParam;

use App\Extensions\ScriptBuilders\Publications\PublicationsScriptDirector;
use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Repositories\Repository;
use App\Lib\Data\Database;
use App\Lib\I18n\Translator;

abstract class PublicationsRepository extends Repository
{
    public function __construct(
        Database $db,
        protected Translator $translator,
        protected PublicationsScriptDirector $director
    ){
        parent::__construct($db);
    }

    public abstract function checkType(int $publicationId) : bool;

    public function save(int $userId, int $publicationId) : void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO saved_publications (user_id, publication_id)
            VALUES (:userId, :publicationId)'
        );

        $stmt->execute([':userId' => $userId, ':publicationId' => $publicationId]);
    }

    public function deleteSave(int $userId, int $publicationId) : void
    {
        $stmt = $this->pdo()->prepare(
            'DELETE FROM saved_publications WHERE user_id = :userId AND publication_id = :publicationId'
        );
        $stmt->execute([':userId' => $userId, ':publicationId' => $publicationId]);
    }   

    protected function applyFilters(PublicationsFilters $filters) : void 
    {
        if (!empty($filters->search))
            $this->director->addSearchTempFilter($filters->search);

        if ($filters->genreId !== null)
            $this->director->addGenreTempFilter($filters->genreId);

        if ($filters->creatorId !== null)
            $this->director->addCreatorTempFilter($filters->creatorId);
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