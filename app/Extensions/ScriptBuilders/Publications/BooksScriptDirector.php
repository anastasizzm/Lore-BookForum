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
        $this->addPublicationExtendedSelectTemp();
        $this->tempBuilder->addSelect("books.category_id,\nbooks.publisher,\nbooks.pages,\nbooks.isbn");
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
}