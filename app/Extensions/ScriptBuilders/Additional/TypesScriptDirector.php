<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Additional;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\BasicModelSortBy;

abstract class TypesScriptDirector extends ScriptDirector
{
    public function __construct()
    {
        parent::__construct("types");
    }

    public function getExistsScript(int $typeId) : ScriptData
    {
        $this->reset();
        $this->builder
            ->addSelect("1")
            ->addWhere("id = :typeId", [':typeId' => ScriptParam::asInt($typeId)]);
        return $this->builder->build();
    }

    public function startTempFilter() : self
    {
        $this->startTemp('types');
        return $this;
    }

    public function addTypeSelectTemp() : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("types.id,\ntypes.title,\ntypes.created_at");
        return $this;
    }

    public function addSearchTempFilter(string $search) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("types.title ILIKE :q", [':q' => ScriptParam::asStr('%' .$search . '%')]);
        return $this;
    }

    public function addOrderTemp(BasicModelSortBy $sort)
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $order = match($sort){
            BasicModelSortBy::Alphabet => 'types.title',
            BasicModelSortBy::Newest => 'types.created_at DESC',
        };
        $this->tempBuilder->setOrder($order);
        return $this;
    }

    public function buildTempFilter() : ScriptData
    {
        return $this->buildTemp();
    }
}