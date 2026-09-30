<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Publications\BooksService;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Exceptions\ValidationException;
use App\Controllers\Controller;

use App\Http\HttpContext;

use App\ErrorCodes;

use App\Constants;

final class PostsController extends Controller
{
    public function __construct(
        private readonly PostsService $postsService
    ){}

    public function list(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTH_TRY), 401, "Authorize first");

        $pageQ = PaginationQuery::fromInput($context->request->query);
        $propsQ = PropertiesQuery::fromInput($context->request->query);
        $searchQ = $context->query('q', '');
        $isbnQ = $context->query('isbn', NULL);

        $parentId = $context->query('parent', 0);
        if (!is_int($parentId) || $parentId == 0)
            $parentId = NULL;

        $publicationId = $context->query('pub', 0);
        if (!is_int($publicationId) || $publicationId == 0)
            $publicationId = NULL;

        $creatorId = $context->query('creator', 0);
        if (!is_int($creatorId) || $creatorId == 0)
            $creatorId = NULL;

        try{
            $paginatedList = $this->postsService->getList($pageQ, $searchQ, $propsQ, $parentId, $publicationId, $creatorId);
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

    public function setLike(HttpContext $context, string $postId)
    {
        $postId = (int)$postId;
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTH_TRY), 401, "Authorize first");

        try{
            $this->postsService->setLike($postId, $userId);
            return $this->jsonEmpty(201);
        }
        catch(ValidationException $e){
            $this->jsonValidationErrors($e->errors());
        }
    }

    public function removeLike(HttpContext $context, string $postId)
    {
        $postId = (int)$postId;
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTH_TRY), 401, "Authorize first");

        try{
            $this->postsService->removeLike($postId, $userId);
            return $this->jsonEmpty(204);
        }
        catch(ValidationException $e){
            $this->jsonValidationErrors($e->errors());
        }
    }
}