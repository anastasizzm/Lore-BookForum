<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Publications;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\ReadingStatus;

final class ArticlesScriptDirector extends PublicationsScriptDirector
{
    public function __construct()
    {
        parent::__construct("articles");
    }

    public function getExistsScript(int $articleId) : ScriptData
    {
        $this->reset();
        $this->builder
            ->addSelect("1")
            ->addWhere("publication_id = :pubId", [':pubId' => ScriptParam::asInt($articleId)]);
        return $this->build();
    }

    public function addArticleSelectTemp() : self
    {
        $this->addPublicationExtendedSelectTemp();
        $this->tempBuilder->addSelect("articles.book_id,\narticles.page_start,\narticles.page_end,\narticles.type_id,\narticles.doi,\narticles.content");
        return $this;
    }

    public function startTempFilter() : self
    {
        $this->startTemp('articles');
        $this->tempBuilder->addJoin('INNER JOIN publications ON publications.id = articles.publication_id');
        
        return $this;
    }

    public function addDoiTempFilter(string $doi) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("articles.doi ILIKE :doi", [':doi' => ScriptParam::asStr($doi . '%')]);
        return $this;
    }

    public function addTypeTempFilter(int $typeId) : self 
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("articles.type_id = :typeId", [':typeId' => ScriptParam::asInt($typeId)]);
        return $this;
    }

    public function addBookTempFilter(int $bookId) : self 
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("articles.book_id IS NOT NULL AND articles.book_id = :bookId", [':bookId' => ScriptParam::asInt($bookId)]);
        return $this;
    }
}