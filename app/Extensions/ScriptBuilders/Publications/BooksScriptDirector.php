<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Publications;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\ReadingStatus;

final class BooksScriptDirector extends PublicationsScriptDirector
{
    public function __construct()
    {
        parent::__construct("books");
    }

    public function getExistsScript(int $bookId) : ScriptData
    {
        $this->reset();
        $this->builder
            ->addSelect("1")
            ->addWhere("publication_id = :pubId", [':pubId' => ScriptParam::asInt($bookId)]);
        return $this->build();
    }

    public function addBookSelectTemp() : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("publications.id,\npublications.title,\npublications.creator_id,\npublications.icon_id,\npublications.created_at,\npublications.genre_id,\npublications.comments_count,\npublications.saved_count,\npublications.rating_avg,\npublications.description,\npublications.author_notes")
            ->addSelect("books.category_id,\nbooks.publisher,\nbooks.pages,\nbooks.isbn,\nbooks.content_id");
        return $this;
    }

    public function startTempFilter() : self
    {
        $this->startTemp('books');
        $this->tempBuilder->addJoin('INNER JOIN publications ON publications.id = books.publication_id');
        
        return $this;
    }

    public function addIsbnTempFilter(string $isbn) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("books.isbn ILIKE :isbn", [':isbn' => ScriptParam::asStr($isbn . '%')]);
        return $this;
    }

    public function addCategoryTempFilter(int $categoryId) : self 
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("books.category_id = :categoryId", [':categoryId' => ScriptParam::asInt($categoryId)]);
        return $this;
    }

    protected function handleFallbackIncluding(string $prop) : void
    {
        switch($prop)
        {
            case 'category':
                $this->tempBuilder->addJoin("INNER JOIN categories ON categories.id = books.category_id")
                    ->addSelect("categories.id as c_id,\ncategories.title as c_title,\ncategories.created_at as c_created_at");
                break;
        }
    }
}