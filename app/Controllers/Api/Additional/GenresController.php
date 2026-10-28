<?php
declare(strict_types=1);

namespace App\Controllers\Api\Additional;

use App\Services\Additional\GenresService;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\GenreForm;

use App\Controllers\Controller;

use App\Http\HttpContext;

use App\Lib\I18n\Translator;
use App\Extensions\Parsers\RouteParamParser;
use App\Exceptions\NotFoundException;
use App\Exceptions\ConflictException;

use App\ErrorCodes;

final class GenresController extends Controller
{
    public function __construct(
        private readonly GenresService $genresService,
        private readonly Translator $translator
    ){}

    public function list(HttpContext $context)
    {
        $query = BasicModelListQuery::fromInput($context->request->query);
        $paginatedList = $this->genresService->getList($query);
        return $this->jsonList($paginatedList->getArray(), [
                'page' => $paginatedList->getPage(),
                'pageSize' => $paginatedList->getPageSize(),
                'hasNext' => $paginatedList->hasNext(),
            ]);
    }

    public function retrieve(HttpContext $context, string $genreId) 
    {
        $id = RouteParamParser::positiveInt(['id' => $genreId], 'id');
        $model = $this->genresService->retrieve($id);
        return $this->jsonObject($model);
    }

    public function create(HttpContext $context)
    {
        $form = GenreForm::fromInput($context->request->body());

        $id = $this->genresService->create($form);
        return $this->jsonCreatedId($id);
    }

    public function update(HttpContext $context, string $genreId)
    {
        $id = RouteParamParser::positiveInt(['id' => $genreId], 'id');
        $form = GenreForm::fromInput($context->request->body());

        $this->genresService->update($id, $form);

        return $this->jsonEmpty();
    }

    public function delete(HttpContext $context, string $genreId)
    {
        $id = RouteParamParser::positiveInt(['id' => $genreId], 'id');

        $this->genresService->delete($id);
        return $this->jsonEmpty(204);
    }
}