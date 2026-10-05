<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Publications\PostsService;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\Publications\PostsListQuery;
use App\Models\Error;
use App\Exceptions\ValidationException;
use App\Controllers\Controller;

use App\Forms\Publications\PostForm;
use App\Extensions\Parsers\RouteParamParser;
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
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $query = PostsListQuery::fromInput($context->request->query);
        $paginatedList = $this->postsService->getListWithContext($query, $userId);
        return $this->jsonList($paginatedList->getArray(), [
                'page' => $paginatedList->getPage(),
                'pageSize' => $paginatedList->getPageSize(),
                'hasNext' => $paginatedList->hasNext(),
            ]);
    }

    public function setLike(HttpContext $context, string $postId)
    {
        $postId = RouteParamParser::positiveInt(['p' => $postId], 'p');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"));

        $this->postsService->setLike($postId, $userId);
        return $this->jsonEmpty(201);
    }

    public function removeLike(HttpContext $context, string $postId)
    {
        $postId = RouteParamParser::positiveInt(['p' => $postId], 'p');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"));

        $this->postsService->removeLike($postId, $userId);
        return $this->jsonEmpty(204);
    }

    public function addComment(HttpContext $context, ?string $postId = NULL)
    {
        $postId = RouteParamParser::optionalPositiveInt(['p' => $postId], 'p');

        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"));

        $formData = PostForm::fromInput($context->request->body());
        $id = $this->postsService->addComment($userId, $formData, $postId);
        return $this->jsonCreatedId($id, statusCode: 201);
    }

    public function removeComment(HttpContext $context, string $postId)
    {
        $postId = RouteParamParser::positiveInt(['p' => $postId], 'p');
        $id = $this->postsService->removeComment($postId);
        return $this->jsonEmpty(204);
    }
}