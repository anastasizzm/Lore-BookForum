<?php
declare(strict_types=1);

namespace App\Repositories\Additional;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\Additional\CategoriesScriptDirector;
use App\Lib\Data\Database;
use App\Lib\I18n\Translator;
use App\Models\BasicModel;
use App\Models\Enums\BasicModelSortBy;
use App\Models\Scripts\ScriptParam;
use App\Services\Configuration\UnitOfWork;
use PDO;

final class CategoriesRepository extends Repository
{
    private readonly CategoriesScriptDirector $director;

    public function __construct(
        private readonly Translator $translator,
        Database $db,
        private readonly UnitOfWork $uow
    ){
        parent::__construct($db);
        $this->director = new CategoriesScriptDirector();
    }

    public function getList(
        int $page,
        int $pageSize,
        BasicModelSortBy $sortBy,
        ?string $search = NULL,
    ) : array
    {
        $this->director->startTempFilter()
            ->addCategorySelectTemp($this->translator->locale(), $this->translator->fallback())
            ->addActiveTempFilter()
            ->addActiveTempFilter();

        if (!empty($search))
            $this->director->addSearchTempFilter($search);

        $this->director->addOrderTemp($sortBy)
            ->setExtraPaginationTemp($page, $pageSize);

        $data = $this->director->buildTempFilter();
        $stmt = $this->executeScript($data);

        $items = array_map(
            static fn(array $row) => BasicModel::fromRow($row),
            $stmt->fetchAll(),
        );

        return $items;
    }

    public function retrieve(int $categoryId) : ?BasicModel 
    {
        $this->director->startTempFilter()
            ->addCategorySelectTemp($this->translator->locale(), $this->translator->fallback())
            ->addConcreteTempFilter($categoryId)->setLimitTemp(1);

        $data = $this->director->buildTempFilter();
        $stmt = $this->executeScript($data);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? NULL : BasicModel::fromRow($row);
    }

    public function create(array $translations) : int
    {
        return $this->uow->transactional(function() use ($translations) {
            $id = (int)$this->execute('INSERT INTO categories DEFAULT VALUES RETURNING id', [])->fetchColumn();
            $this->saveTranslations($id, $translations);
            return $id;
        });
    }

    public function update(int $id, array $translations) : bool
    {
        return $this->uow->transactional(function() use ($id, $translations) {
            $params = [':id' => ScriptParam::asInt($id)];
            $values = [];
            $codes = [];
            $i = 0;

            foreach ($translations as $code => $title) {
                $values[] = "(:id_{$i}, :code_{$i}, :title_{$i})";
                $params[":id_{$i}"] = ScriptParam::asInt($id);
                $params[":code_{$i}"] = ScriptParam::asStr((string)$code);
                $params[":title_{$i}"] = ScriptParam::asStr($title);
                $codes[] = ":code_{$i}";
                $i++;
            }

            $valuesSql = implode(', ', $values);
            $codesSql = empty($codes) ? 'NULL' : implode(', ', $codes);

            $sql = "
                WITH locked AS (
                    SELECT id FROM categories WHERE id = :id FOR UPDATE
                )
            ";

            if (!empty($valuesSql)) {
                $sql .= ",
                    upserted AS (
                    INSERT INTO categories_translations (category_id, code, title) 
                    SELECT v.category_id::int, v.code::varchar, v.title::varchar
                    FROM (VALUES {$valuesSql}) AS v(category_id, code, title)
                    WHERE EXISTS (SELECT 1 FROM locked)
                    ON CONFLICT (category_id, code) DO UPDATE SET title = EXCLUDED.title
                ),
                deleted AS (
                    DELETE FROM categories_translations 
                    WHERE category_id = :id AND code NOT IN ({$codesSql})
                      AND EXISTS (SELECT 1 FROM locked)
                )
                ";
            } else {
                $sql .= ",
                deleted AS (
                    DELETE FROM categories_translations 
                    WHERE category_id = :id 
                      AND EXISTS (SELECT 1 FROM locked)
                )
                ";
            }

            $sql .= " SELECT 1 FROM locked";

            $exists = $this->execute($sql, $params)->fetchColumn();
            
            return $exists !== false;
        });
    }

    private function saveTranslations(int $id, array $translations) : void
    {
        if (empty($translations)) {
            return;
        }

        $values = [];
        $params = [];
        $i = 0;

        foreach ($translations as $code => $title) {
            $values[] = "(:id_{$i}, :code_{$i}, :title_{$i})";
            $params[":id_{$i}"] = ScriptParam::asInt($id);
            $params[":code_{$i}"] = ScriptParam::asStr((string)$code);
            $params[":title_{$i}"] = ScriptParam::asStr($title);
            $i++;
        }

        $sql = 'INSERT INTO categories_translations (category_id, code, title) VALUES ' 
             . implode(', ', $values) 
             . ' ON CONFLICT (category_id, code) DO UPDATE SET title = EXCLUDED.title';

        $this->execute($sql, $params);
    }

    public function delete(int $id) : bool
    {
        $stmt = $this->execute('UPDATE categories SET deleted_at = NOW() WHERE id = :id', [':id' => ScriptParam::asInt($id)]);
        return $stmt->rowCount() > 0;
    }
}