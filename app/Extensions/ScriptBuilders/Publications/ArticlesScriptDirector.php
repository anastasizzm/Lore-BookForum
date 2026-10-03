<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Publications;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScripParam;

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
        return $this->builder->build();
    }

    public function addArticleSelectTemp() : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("publications.id,\npublications.title,\npublications.creator_id,\npublications.icon_id,\npublications.created_at,\npublications.genre_id,\npublications.comments_count,\npublications.saved_count,\npublications.rating_avg,\npublications.description,\npublications.author_notes")
            ->addSelect("articles.book_id,\narticles.page_start,\narticles.page_end,\narticles.type_id,\narticles.doi,\narticles.content");
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
        $this->tempBuilder->addWhere("articles.doi ILIKE :doi", [':doi' => ScriptParam::asStr($doi)]);
        return $this;
    }

    protected function handleFallbackIncluding(string $prop) : void
    {
        switch($prop)
        {
            case 'book':
                $this->tempBuilder->addJoin("LEFT JOIN publications book ON book.id = articles.publication_id")
                    ->addSelect("book.id as pub_id,\nbook.title as pub_title,\nbook.icon_id as pub_icon_id,\nbook.created_at as pub_created_at");
                break;
            case 'type':
                $this->tempBuilder->addJoin("LEFT JOIN types ON types.id = articles.type_id")
                    ->addSelect("types.id as t_id,\ntypes.title as t_title,\ntypes.created_at as t_created_at");
                break;
        }
    }
}