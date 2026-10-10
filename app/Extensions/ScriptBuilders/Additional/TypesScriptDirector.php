<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Additional;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\BasicModelSortBy;

final class TypesScriptDirector extends ScriptDirector
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
        return $this->build();
    }

    public function startTempFilter() : self
    {
        $this->startTemp('types');
        $this->tempBuilder->addJoin("INNER JOIN types_translations ON types_translations.type_id = types.id")
            ->addJoin("INNER JOIN languages ON languages.code = types_translations.code AND languages.is_active");
        return $this;
    }

    public function addTypeSelectTemp(string $currentLocale, string $fallbackLocale) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("types.id,\ntypes.created_at")
            ->addSelect("COALESCE(
                    MAX(CASE WHEN types_translations.code = :currentCulture THEN types_translations.title END),
                    MAX(CASE WHEN types_translations.code = :fallbackCulture THEN types_translations.title END)
                ) as title", [':currentCulture' => ScriptParam::asStr($currentLocale), ':fallbackCulture' => ScriptParam::asStr($fallbackLocale)])
            ->setGroup("types.id");;
        return $this;
    }

    public function addSearchTempFilter(string $search) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("types_translations.title ILIKE :q", [':q' => ScriptParam::asStr('%' .$search . '%')]);
        return $this;
    }

    public function addOrderTemp(BasicModelSortBy $sort)
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $order = match($sort){
            BasicModelSortBy::Alphabet => 'title',
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