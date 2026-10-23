<?php
declare(strict_types=1);

namespace App\Repositories\Additional;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\Additional\TypesScriptDirector;
use App\Lib\Data\Database;
use App\Lib\I18n\Translator;
use App\Models\BasicModel;
use App\Models\Enums\BasicModelSortBy;
use App\Services\Configuration\UnitOfWork;

final class TypesRepository extends Repository
{
    private readonly TypesScriptDirector $director;

    public function __construct(
        private readonly Translator $translator,
        Database $db,
        private readonly UnitOfWork $uow
    ){
        parent::__construct($db);
        $this->director = new TypesScriptDirector();
    }

    public function getList(
        int $page,
        int $pageSize,
        BasicModelSortBy $sortBy,
        ?string $search = NULL,
    ) : array
    {
        $this->director->startTempFilter()->addTypeSelectTemp($this->translator->locale(), $this->translator->fallback());

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
            $id = (int)$this->execute('INSERT INTO types DEFAULT VALUES RETURNING id', [])->fetchColumn();
            $this->saveTranslations($id, $translations);
            return $id;
        });
    }

    public function update(int $id, array $translations) : bool
    {
        return $this->uow->transactional(function() use ($id, $translations) {
            $exists = $this->execute('SELECT 1 FROM types WHERE id = :id', [':id' => $id])->fetchColumn();
            if ($exists === false) {
                return false;
            }
            $this->saveTranslations($id, $translations);
            return true;
        });
    }

    private function saveTranslations(int $id, array $translations) : void
    {
        foreach ($translations as $code => $title) {
            $this->execute(
                'INSERT INTO types_translations (type_id, code, title) VALUES (:id, :code, :title)
                 ON CONFLICT (type_id, code) DO UPDATE SET title = EXCLUDED.title',
                [':id' => $id, ':code' => (string)$code, ':title' => $title]
            );
        }
    }

    public function delete(int $id) : bool
    {
        return $this->uow->transactional(function() use ($id) {
            $stmt = $this->execute('DELETE FROM types WHERE id = :id', [':id' => $id]);
            return $stmt->rowCount() > 0;
        });
    }
}