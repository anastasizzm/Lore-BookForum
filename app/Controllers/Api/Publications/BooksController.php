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
use App\Models\Enums\ServiceType;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Attributes\Inject;

use App\Controllers\Controller;

use App\Http\HttpContext;
use App\Exceptions\ValidationException;

use App\ErrorCodes;
use App\Constants;

final class BooksController extends PublicationsController
{
    public function __construct(
        #[Inject(key: ServiceType::Publication)]
        CommonRuleProvider $ruleProvider,
        BooksService $booksService
    ){
        parent::__construct($ruleProvider, $booksService);
    }

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
}