<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Publications\ArticlesService;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;
use App\Models\Queries\TypeQuery;
use App\Models\Queries\StatusQuery;

use App\Controllers\Controller;

use App\Http\HttpContext;
use App\Exceptions\ValidationException;

use App\ErrorCodes;
use App\Constants;

final class ArticlesController extends Controller
{
    public function __construct(
        private readonly ArticlesService $articlesService
    ){}

    public function list(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(Error::fromMessage(ErrorCodes::UNAUTH_TRY, "Authorize first"), 401, "Authorize first");

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromInput($context->request->query);
        $sortQ = SortQuery::fromInput($context->request->query);
        $statusQ = StatusQuery::fromInput($context->request->query);
        $typeQ = TypeQuery::fromInput($context->request->query);
        $searchQ = $context->query('q', '');
        $doi = $context->query('doi', NULL);

        $genreId = $context->query('genre', 0);
        if (!is_int($genreId) || $genreId == 0)
            $genreId = NULL;

        $creatorId = $context->query('creator', 0);
        if (!is_int($creatorId) || $creatorId == 0)
            $creatorId = NULL;

        $bookId = $context->query('book', 0);
        if (!is_int($bookId) || $bookId == 0)
            $bookId = NULL;

        try{
            $paginatedList = $this->articlesService->getList($pageQ, $searchQ, $propsQ, $userId, false, $sortQ, $statusQ, $genreId, $creatorId, $bookId, $doi, $typeQ);
            return $this->jsonList($paginatedList->getArray(), [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                ]);
        }
        catch(ValidationException $e){
            return $this->jsonValidationErrors($e->errors());
        }
    }

    public function savedList(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(Error::fromMessage(ErrorCodes::UNAUTH_TRY, "Authorize first"), 401, "Authorize first");

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromInput($context->request->query);
        $sortQ = SortQuery::fromInput($context->request->query);
        $statusQ = StatusQuery::fromInput($context->request->query);
        $typeQ = TypeQuery::fromInput($context->request->query);
        $searchQ = $context->query('q', '');
        $doi = $context->query('doi', NULL);

        $genreId = $context->query('genre', 0);
        if (!is_int($genreId) || $genreId == 0)
            $genreId = NULL;

        $creatorId = $context->query('creator', 0);
        if (!is_int($creatorId) || $creatorId == 0)
            $creatorId = NULL;

        $bookId = $context->query('book', 0);
        if (!is_int($bookId) || $bookId == 0)
            $bookId = NULL;

        try{
            $paginatedList = $this->articlesService->getList($pageQ, $searchQ, $propsQ, $userId, true, $sortQ, $statusQ, $genreId, $creatorId, $bookId, $doi, $typeQ);
            return $this->jsonList($paginatedList->getArray(), [
                    'page' => $paginatedList->getPage(),
                    'pageSize' => $paginatedList->getPageSize(),
                    'hasNext' => $paginatedList->hasNext(),
                ]);
        }
        catch(ValidationException $e){
            return $this->jsonValidationErrors($e->errors());
        }
    }

    public function save(HttpContext $context, string $articleId)
    {
        $articleId = (int)$articleId;
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(Error::fromMessage(ErrorCodes::UNAUTH_TRY, "Authorize first"), 401, "Authorize first");

        try
        {
            $this->articlesService->save($userId, $articleId);
            return $this->jsonEmpty(201);
        }
        catch(ValidationException $e){
            return $this->jsonValidationErrors($e->errors());
        }
    }

    public function deleteSave(HttpContext $context, string $articleId)
    {
        $articleId = (int)$articleId;
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(Error::fromMessage(ErrorCodes::UNAUTH_TRY, "Authorize first"), 401, "Authorize first");

        try
        {
            $this->articlesService->deleteSave($userId, $articleId);
            return $this->jsonEmpty(204);
        }
        catch(ValidationException $e){
            return $this->jsonValidationErrors($e->errors());
        }
    }
}