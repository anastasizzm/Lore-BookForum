<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Permissions\PublicationPermissionsService;
use App\Services\Publications\ArticlesService;

use App\Models\Queries\Publications\ArticlesListQuery;
use App\Extensions\Parsers\RouteParamParser;
use App\Forms\Publications\ArticleForm;
use App\Forms\Publications\ArticlePatchForm;
use App\Models\Enums\ServiceType;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Attributes\Inject;

use App\Controllers\Controller;

use App\Http\HttpContext;
use App\Exceptions\ValidationException;

use App\ErrorCodes;
use App\Constants;

final class ArticlesController extends PublicationsController
{
    public function __construct(
        #[Inject(key: ServiceType::Publication)]
        CommonRuleProvider $ruleProvider,
        ArticlesService $articlesService,
        PublicationPermissionsService $permsService
    ){
        parent::__construct($ruleProvider, $articlesService, $permsService);
    }

    public function list(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $query = ArticlesListQuery::fromInput($context->request->query, $userId);
        $paginatedList = $this->service->getListWithContext($query);
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
        $articleId = $this->service->create(
            ArticleForm::fromInput($formData),
            $userId
        );
        return $this->jsonCreatedId($articleId);
    }

    public function update(HttpContext $context, string $id) 
    {
        $articleId = RouteParamParser::positiveInt(['id' => $id], 'id');

        $formData = $context->request->body();
        $this->service->update($articleId, ArticlePatchForm::fromInput($formData));
        return $this->jsonEmpty();
    }
}