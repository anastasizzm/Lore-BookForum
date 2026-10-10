<?php
declare(strict_types=1);

namespace App\Repositories\Additional;

use App\Repositories\Repository;
use App\Extensions\ScriptBuilders\Additional\GenresScriptDirector;

use PDO;
use App\Lib\Data\Database;
use App\Lib\I18n\Translator;

use App\Models\Additional\Language;

final class LanguagesRepository extends Repository
{
    public function __construct(
        Database $db
    ){
        parent::__construct($db);
    }

    public function getList(
        int $page,
        int $pageSize,
    ) : array
    {
        $stmt = $this->pdo()->prepare('SELECT code, is_active FROM languages LIMIT :limit OFFSET :offset');
        $stmt->execute([':limit' => $page+1, ':offset' => ($page-1) * $pageSize]);

        $items = array_map(
            static fn(array $row) => Language::fromRow($row),
            $stmt->fetchAll(),
        );

        return $items;
    }
}