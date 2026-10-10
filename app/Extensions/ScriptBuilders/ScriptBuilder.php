<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders;

use App\Models\Scripts\ScriptParam;

class ScriptBuilder
{
    private array $selectClauses;
    private array $joinClauses;
    private array $whereClauses;
    private array $havingClauses;

    private array $params;

    private string $groupBy;
    private string $orderBy;
    private string $limit;

    private string $table;
    private ?string $alias;

    private ?string $cache;

    private function clearCache()
    {
        $this->cache = NULL;
    }

    protected function __construct(
        string $table = '',
        ?string $alias = NULL
    ){
        $this->table = $table;
        $this->alias = $alias;

        $this->cache = NULL;
        $this->selectClauses = [];
        $this->joinClauses = [];
        $this->whereClauses = [];
        $this->havingClauses = [];
        $this->params = [];

        $this->groupBy = '';
        $this->orderBy = '';
        $this->limit = '';
    }

    public static function withEmpty() : self
    {
        return new self();
    }

    public static function withTable(string $table, ?string $alias = NULL) : self
    {
        return new self($table, $alias);
    }

    // --- setters

    public function setFrom(string $table, ?string $alias = NULL, array $params = []) : self
    {
        $this->table = $table;
        if (!empty($alias)) $this->alias = $alias;
        $this->populateParams($params);
        $this->clearCache();
        return $this;
    }

    public function addSelect(string $select, array $params = []) : self
    {
        $this->selectClauses[] = $select;
        $this->populateParams($params);
        $this->clearCache();

        return $this;
    }

    public function addJoin(string $join, array $params = []) : self
    {
        $this->joinClauses[] = $join;
        $this->populateParams($params);
        $this->clearCache();
        return $this;
    } 

    public function addWhere(string $where, array $params = []) : self
    {
        $this->whereClauses[] = $where;
        $this->populateParams($params);
        $this->clearCache();
        return $this;
    }

    public function addHaving(string $having, array $params = []) : self
    {
        $this->havingClauses[] = $having;
        $this->populateParams($params);
        $this->clearCache();
        return $this;
    }
 
    public function setOrder(string $order, array $params = []) : self
    {
        $this->orderBy = $order;
        $this->populateParams($params);
        $this->clearCache();
        return $this;
    }

    public function setGroup(string $group, array $params = []) : self
    {
        $this->groupBy = $group;
        $this->populateParams($params);
        $this->clearCache();
        return $this;
    }

    public function setLimit(int $limit, ?int $offset = NULL) : self
    {
        $this->limit = "LIMIT :limit";
        $this->populateParams([':limit' => ScriptParam::asInt($limit)]);
        if (!empty($offset))
        {
            $this->limit .= " OFFSET :offset";
            $this->populateParams([':offset' => ScriptParam::asInt($offset)]);
        }
        
        $this->clearCache();
        return $this;
    }

    private function populateParams(array $params) : void
    {
        foreach($params as $key => $value)
            $this->params[$key] = $value;
    }


    // --- getters

    public function getSelect() : string
    {
        $clausesIsEmpty = empty($this->selectClauses);

        $select = empty($this->selectClauses)
            ? "SELECT *"
            : "SELECT " . implode(",\n", $this->selectClauses);
        
        if (empty($this->table)) return $select;
        else return $select . ' FROM ' . $this->table . (empty($this->alias) ? '' : (' ' . $this->alias));
    }

    public function getWhereAnd() : string
    {
        return $this->getWhere('AND');
    }

    public function getWhereOr() : string
    {
        return $this->getWhere('OR');
    }

    private function getWhere(string $delimiter) : string
    {
        $where = implode(" $delimiter\n", $this->whereClauses);
        if (!empty($where)) $where = 'WHERE ' . $where;
        return $where;
    }

    public function getHavingAnd() : string
    {
        return $this->getHaving('AND');
    }

    public function getHavingOr() : string
    {
        return $this->getHaving('OR');
    }

    private function getHaving(string $delimiter) : string
    {
        $having = implode(" $delimiter\n", $this->havingClauses);
        if (!empty($having)) $having = 'HAVING ' . $having;
        return $having;
    }

    public function getJoins() : string
    {
        return implode("\n", $this->joinClauses);
    }

    public function getOrderBy() : string 
    {
        if (!empty($this->orderBy)) return 'ORDER BY ' . $this->orderBy;
        return '';
    }

    public function getGroupBy() : string 
    {
        if (!empty($this->groupBy)) return 'GROUP BY ' . $this->groupBy;
        return '';
    }

    public function getLimit() : string
    {
        return $this->limit;
    }

    public function getParams() : array
    {
        return $this->params;
    }

    public function build() : string 
    {
        if (!empty($cache)) return $this->cache;

        $select = $this->getSelect();
        $joins = $this->getJoins();
        $where = $this->getWhereAnd();
        $group = $this->getGroupBy();
        $order = $this->getOrderBy();
        $having = $this->getHavingAnd();
        $limit = $this->getLimit();

        $sql = "
            $select
            $joins
            $where
            $group
            $order
            $having
            $limit
        ";
        $this->cache = $sql;
        return $sql;
    }
}