<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Publications\BooksService;

use App\Models\Queries\PaginationQuery;
use App\Models\Queries\PropertiesQuery;
use App\Models\Queries\SortQuery;
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

    public function setLike(HttpContext $context, $postId)
    {
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

    public function removeLike(HttpContext $context, $postId)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTH_TRY), 401, "Authorize first");

        try{
            $this->postsService->removeLike($postId, $userId);
            return $this->jsonEmpty(201);
        }
        catch(ValidationException $e){
            $this->jsonValidationErrors($e->errors());
        }
    }
}