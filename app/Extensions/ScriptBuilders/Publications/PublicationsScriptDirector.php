<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Publications;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\ReadingStatus;
use App\Models\Enums\PublicationsSortBy;

abstract class PublicationsScriptDirector extends ScriptDirector
{
    public function __construct(string $tableName, ?string $alias = NULL)
    {
        parent::__construct($tableName, $alias);
    }

    public function startTempFilter() : self
    {
        $this->startTemp('publications');
        return $this;
    }

    public function addPublicationShortSelectTemp() : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("publications.id,\npublications.title,\npublications.icon_id,\npublications.created_at");
        return $this;
    }

    public function addPublicationSelectTemp() : self
    {
        $this->addPublicationShortSelectTemp();
        $this->tempBuilder->addSelect("publications.genre_id,\npublications.creator_id");
        
        return $this;
    }

    public function addPublicationExtendedSelectTemp() : self
    {
        $this->addPublicationSelectTemp();
        $this->tempBuilder->addSelect("publications.comments_count,\npublications.saved_count,\npublications.rating_avg,\npublications.description,\npublications.author_notes");
        return $this;
    }

    public function addConcreteTempFilter(int $publicationId) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("publications.id = :pubId", [':pubId' => ScriptParam::asInt($publicationId)]);
        return $this;
    }

    public function addSearchTempFilter(string $search) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("publications.title ILIKE :q", [':q' => ScriptParam::asStr('%' .$search . '%')]);
        return $this;
    }

    public function addGenreTempFilter(int $genreId) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("publications.genre_id = :genreId", [':genreId' => ScriptParam::asInt($genreId)]);
        return $this;
    }

    public function addCreatorTempFilter(int $creatorId) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("publications.creator_id = :creatorId", [':creatorId' => ScriptParam::asInt($creatorId)]);
        return $this;
    }

    public function addReadingStatusTempFilter(ReadingStatus $status, int $userId) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();

        $statusWhere = match($status){
            ReadingStatus::None => '',
            ReadingStatus::Reading => 'users_read.publication_id IS NOT NULL AND NOT users_read.is_closed',
            ReadingStatus::Ended => 'users_read.publication_id IS NOT NULL AND users_read.is_closed'
        };
        if (empty($statusWhere)) return $this;

        $this->tempBuilder->addJoin("LEFT JOIN users_read ON users_read.user_id = :urUserId AND users_read.publication_id = publications.id", 
            [':urUserId' => ScriptParam::asInt($userId)]
        )->addWhere($statusWhere);
        return $this;
    }

    public function addSavedOnlyTempFilter(int $userId) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addJoin('INNER JOIN saved_publications ON saved_publications.user_id = :spUserId AND saved_publications.publication_id = publications.id',
            [':spUserId' => ScriptParam::asInt($userId)]
        );

        return $this;
    }

    public function addOrderTemp(PublicationsSortBy $sort)
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $order = match($sort){
            PublicationsSortBy::Popularity => 'publications.rating_avg DESC',
            PublicationsSortBy::Newest => 'publications.created_at DESC',
            PublicationsSortBy::Alphabet => 'publications.title'
        };
        $this->tempBuilder->setOrder($order);
        return $this;
    }

    public function buildTempFilter() : ScriptData
    {
        return $this->buildTemp();
    }
}