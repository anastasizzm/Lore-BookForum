<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Publications\ArticlesService;

use App\Models\Queries\Publications\ArticlesListQuery;
use App\Extensions\Parsers\RouteParamParser;
use App\Models\Enums\ServiceType;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Attributes\Inject;

use App\Controllers\Controller;

use App\Http\HttpContext;
use App\Exceptions\ValidationException;

use App\ErrorCodes;
use App\Constants;

final class ArticlesController extends Controller
{
    public function __construct(
        #[Inject(key: ServiceType::Publication)]
        private readonly CommonRuleProvider $ruleProvider,
        private readonly ArticlesService $articlesService
    ){}

    public function list(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $query = ArticlesListQuery::fromInput($context->request->query, $userId);
        $paginatedList = $this->articlesService->getListWithContext($query);
        return $this->jsonList($paginatedList->getArray(), [
                'page' => $paginatedList->getPage(),
                'pageSize' => $paginatedList->getPageSize(),
                'hasNext' => $paginatedList->hasNext(),
            ]);
    }

    public function save(HttpContext $context, string $articleId)
    {
        $articleId = RouteParamParser::positiveInt(['a' => $articleId], 'a');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);
    
        $this->articlesService->save($userId, $articleId);
        return $this->jsonEmpty(201);
    }

    public function deleteSave(HttpContext $context, string $articleId)
    {
        $articleId = RouteParamParser::positiveInt(['a' => $articleId], 'a');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);
    
        $this->articlesService->deleteSave($userId, $articleId);
        return $this->jsonEmpty(204);
    }

    public function rate(HttpContext $context, string $articleId)
    {
        $articleId = RouteParamParser::positiveInt(['a' => $articleId], 'a');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $formData = $context->request->body();
        $this->articlesService->rate($userId, $articleId, RateForm::fromInput($formData));
        return $this->jsonEmpty(201);
    }

    public function unrate(HttpContext $context, string $articleId)
    {
        $articleId = RouteParamParser::positiveInt(['a' => $articleId], 'a');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $this->articlesService->unrate($userId, $articleId);
        return $this->jsonEmpty(204);
    }
}