<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Publications\BooksService;

use App\Models\Queries\Publications\BooksListQuery;
use App\Models\Publications\BookFiles;
use App\Forms\Publications\BookForm;
use App\Forms\Publications\RateForm;
use App\Forms\Publications\BookProgressForm;
use App\Models\Enums\BookFormat;
use App\Extensions\Parsers\RouteParamParser;
use App\Extensions\Parsers\QueryParser;

use App\Controllers\Controller;

use App\Http\HttpContext;
use App\Exceptions\ValidationException;

use App\ErrorCodes;
use App\Constants;

final class BooksController extends Controller
{
    public function __construct(
        private readonly BooksService $booksService
    ){}

    public function list(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $query = BooksListQuery::fromInput($context->request->query, $userId);
        $paginatedList = $this->booksService->getListWithContext($query);
        return $this->jsonList($paginatedList->getArray(), [
                'page' => $paginatedList->getPage(),
                'pageSize' => $paginatedList->getPageSize(),
                'hasNext' => $paginatedList->hasNext(),
            ]);
    }
    
    public function save(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);
    
        $this->booksService->save($userId, $bookId);
        return $this->jsonEmpty(201);
    }

    public function deleteSave(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $this->booksService->deleteSave($userId, $bookId);
        return $this->jsonEmpty(204);
    }

    public function rate(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $formData = $context->request->body();
        $this->booksService->rate($userId, $bookId, RateForm::fromInput($formData));
        return $this->jsonEmpty(201);
    }

    public function unrate(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $this->booksService->unrate($userId, $bookId);
        return $this->jsonEmpty(204);
    }

    public function create(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $formData = $context->request->body();
        $bookId = $this->booksService->create(
            BookForm::fromInput($formData),
            $userId
        );
        return $this->jsonCreatedId($bookId);
    }

    public function upsert(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);
            
        $formData = $context->request->body();
        $this->booksService->saveProgress($userId, $bookId, BookProgressForm::fromInput($formData));
        return $this->jsonEmpty();
    }

    public function finish(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $format = QueryParser::enum($context->request->body(), 'format', BookFormat::class);
        $this->booksService->finishReading($userId, $bookId, $format);
        return $this->jsonEmpty();
    }

    public function reset(HttpContext $context, string $bookId)
    {
        $bookId = RouteParamParser::positiveInt(['b' => $bookId], 'b');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $format = QueryParser::optionalEnum($context->request->body(), 'format', BookFormat::class);
        $this->booksService->resetProgress($userId, bookId, $format);
        return $this->jsonEmpty();
    }
}