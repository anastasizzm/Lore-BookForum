<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Additional;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\BasicModelSortBy;

final class CategoriesScriptDirector extends ScriptDirector
{
    public function __construct()
    {
        parent::__construct("categories");
    }

    public function getExistsScript(int $categoryId) : ScriptData
    {
        $this->reset();
        $this->builder
            ->addSelect("1")
            ->addWhere("id = :categoryId", [':categoryId' => ScriptParam::asInt($categoryId)]);
        return $this->build();
    }

    public function startTempFilter() : self
    {
        $this->startTemp('categories');
        $this->tempBuilder->addJoin("INNER JOIN categories_translations ON categories_translations.genre_id = categories.id")
            ->addJoin("INNER JOIN languages ON languages.code = categories_translations.code AND languages.is_active");
        return $this;
    }

    public function addCategorySelectTemp(string $currentLocale, string $fallbackLocale) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("categories.id,\ncategories.created_at")
            ->addSelect("COALESCE(
                    MAX(CASE WHEN categories_translations.code = :currentCulture THEN categories_translations.title END),
                    MAX(CASE WHEN categories_translations.code = :fallbackCulture THEN categories_translations.title END)
                ) as title", [':currentCulture' => ScriptParam::asStr($currentLocale), ':fallbackCulture' => ScriptParam::asStr($fallbackLocale)])
            ->setGroup("categories.id");
        
        return $this;
    }

    public function addSearchTempFilter(string $search) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("categories_translations.title ILIKE :q", [':q' => ScriptParam::asStr('%' .$search . '%')]);
        return $this;
    }

    public function addOrderTemp(BasicModelSortBy $sort)
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $order = match($sort){
            BasicModelSortBy::Alphabet => 'title',
            BasicModelSortBy::Newest => 'categories.created_at DESC',
        };
        $this->tempBuilder->setOrder($order);
        return $this;
    }

    public function buildTempFilter() : ScriptData
    {
        return $this->buildTemp();
    }
}