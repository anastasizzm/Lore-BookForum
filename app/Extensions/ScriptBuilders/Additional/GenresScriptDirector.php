<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Additional;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\BasicModelSortBy;

abstract class GenresScriptDirector extends ScriptDirector
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
        return $this->builder->build();
    }

    public function startTempFilter() : self
    {
        $this->startTemp('genres');
        return $this;
    }

    public function addGenreSelectTemp() : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("genres.id,\ngenres.title,\ngenres.created_at");
        return $this;
    }

    public function addSearchTempFilter(string $search) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("genres.title ILIKE :q", [':q' => ScriptParam::asStr('%' .$search . '%')]);
        return $this;
    }

    public function addOrderTemp(BasicModelSortBy $sort)
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $order = match($sort){
            BasicModelSortBy::Alphabet => 'genres.title',
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