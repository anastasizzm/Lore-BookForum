<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders;

use App\Extensions\ScriptBuilders;
use App\Models\Scripts\ScriptData;

use RuntimeException;

abstract class ScriptDirector
{
    protected ScriptBuilder $builder;
    protected ?ScriptBuilder $tempBuilder;

    private readonly ?string $table;
    private readonly ?string $alias;

    public function __construct(?string $table = NULL, ?string $alias = NULL)
    {
        $this->table = $table;
        $this->alias = $alias;

        $this->reset();
    }

    protected function reset() : void 
    {
        if (empty($this->table)) $this->builder = ScriptBuilder::withEmpty();
        else $this->builder = ScriptBuilder::withTable($this->table, $this->alias);
    }

    protected function build() : ScriptData
    {
        $sql = $this->builder->build();
        $params = $this->builder->getParams();
        return new ScriptData($sql, $params);
    }

    protected function startTemp(?string $table = NULL, ?string $alias = NULL) : void
    {
        if (empty($table)) $this->tempBuilder = ScriptBuilder::withEmpty();
        else $this->tempBuilder = ScriptBuilder::withTable($table, $alias);
    }

    public function setLimitTemp(int $limit, ?int $offset = NULL) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->setLimit($limit, $offset);
        return $this;
    }

    public function setPaginationTemp(int $pageNum, int $pageSize) : self
    {
        return $this->setLimitTemp($pageSize, ($pageNum - 1) * $pageSize);
    }

    public function setExtraPaginationTemp(int $pageNum, int $pageSize) : self
    {
        return $this->setLimitTemp($pageSize + 1, ($pageNum - 1) * $pageSize);
    }

    protected function isTempStarted() : bool { return $this->tempBuilder !== null; }

    protected function buildTemp() : ScriptData
    {
        if ($this->tempBuilder === null)
            throw new RuntimeException("Init temporary builder first with startTemp()");

        $sql = $this->tempBuilder->build();
        $params = $this->tempBuilder->getParams();
        return new ScriptData($sql, $params);
    }

    public function getTempBuilder() : ScriptBuilder 
    {
        if ($this->tempBuilder === null)
            throw new RuntimeException("Init temporary builder first with startTemp()");

        return $this->tempBuilder->clone();
    } 

    public abstract function getExistsScript(int $publicationId) : ScriptData;
}