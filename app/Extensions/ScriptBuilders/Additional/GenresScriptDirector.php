<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Additional;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\BasicModelSortBy;

final class GenresScriptDirector extends ScriptDirector
{
    public function __construct()
    {
        parent::__construct("genres");
    }

    public function getExistsScript(int $genreId) : ScriptData
    {
        $this->reset();
        $this->builder
            ->addSelect("1")
            ->addWhere("id = :genreId", [':genreId' => ScriptParam::asInt($genreId)]);
        return $this->build();
    }

    public function startTempFilter() : self
    {
        $this->startTemp('genres');
        $this->tempBuilder->addJoin("INNER JOIN genres_translations ON genres_translations.genre_id = genres.id")
            ->addJoin("INNER JOIN languages ON languages.code = genres_translations.code AND languages.is_active");
        return $this;
    }

    public function addGenreSelectTemp(string $currentLocale, string $fallbackLocale) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("genres.id,\ngenres.created_at")
            ->addSelect("COALESCE(
                    MAX(CASE WHEN genres_translations.code = :currentCulture THEN genres_translations.title END),
                    MAX(CASE WHEN genres_translations.code = :fallbackCulture THEN genres_translations.title END)
                ) as title", [':currentCulture' => ScriptParam::asStr($currentLocale), ':fallbackCulture' => ScriptParam::asStr($fallbackLocale)])
            ->setGroup("genres.id");
        return $this;
    }

    public function addSearchTempFilter(string $search) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("genres_translations.title ILIKE :q", [':q' => ScriptParam::asStr('%' .$search . '%')]);
        return $this;
    }

    public function addOrderTemp(BasicModelSortBy $sort)
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $order = match($sort){
            BasicModelSortBy::Alphabet => 'title',
            BasicModelSortBy::Newest => 'genres.created_at DESC',
        };
        $this->tempBuilder->setOrder($order);
        return $this;
    }

    public function buildTempFilter() : ScriptData
    {
        return $this->buildTemp();
    }
}