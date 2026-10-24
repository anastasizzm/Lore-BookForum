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
        $this->director->startTempFilter()->addCategorySelectTemp($this->translator->locale(), $this->translator->fallback());

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
        $this->saveTranslations($id, $translations);
        return true;
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
        $stmt = $this->execute('DELETE FROM categories WHERE id = :id', [':id' => ScriptParam::asInt($id)]);
        return $stmt->rowCount() > 0;
    }
}