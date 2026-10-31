<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Services\Publications\PublicationsService;
use App\Services\Permissions\PublicationPermissionsService;

use App\Forms\Publications\RateForm;
use App\Forms\Publications\ReadProgressForm;
use App\Models\Enums\BookFormat;
use App\Models\Queries\Permissions\RolesUserListQuery;
use App\Extensions\Parsers\QueryParser;
use App\Extensions\Parsers\RouteParamParser;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;

use App\Controllers\Controller;

use App\Http\HttpContext;
use App\Exceptions\ValidationException;

use App\ErrorCodes;
use App\Constants;

abstract class PublicationsController extends Controller
{
    public function __construct(
        protected readonly CommonRuleProvider $ruleProvider,
        protected readonly PublicationsService $service,
        private readonly PublicationPermissionsService $permsService
    ){}
    
    public function save(HttpContext $context, string $id)
    {
        $publicationId = RouteParamParser::positiveInt(['id' => $id], 'id');
        
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);
    
        $this->service->save($userId, $publicationId);
        return $this->jsonEmpty(201);
    }

    public function deleteSave(HttpContext $context, string $id)
    {
        $publicationId = RouteParamParser::positiveInt(['id' => $id], 'id');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $this->service->deleteSave($userId, $publicationId);
        return $this->jsonEmpty(204);
    }

    public function rate(HttpContext $context, string $id)
    {
        $publicationId = RouteParamParser::positiveInt(['id' => $id], 'id');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $formData = $context->request->body();
        $this->service->rate($userId, $publicationId, RateForm::fromInput($formData));
        return $this->jsonEmpty(201);
    }

    public function unrate(HttpContext $context, string $id)
    {
        $publicationId = RouteParamParser::positiveInt(['id' => $id], 'id');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $this->service->unrate($userId, $publicationId);
        return $this->jsonEmpty(204);
    }

    public function upsert(HttpContext $context, string $id)
    {
        $publicationId = RouteParamParser::positiveInt(['id' => $id], 'id');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);
            
        $formData = $context->request->body();
        $this->service->saveProgress($userId, $publicationId, ReadProgressForm::fromInput($formData));
        return $this->jsonEmpty();
    }

    public function finish(HttpContext $context, string $id)
    {
        $publicationId = RouteParamParser::positiveInt(['id' => $id], 'id');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $format = QueryParser::enum($context->request->body(), 'format', BookFormat::class);
        $this->service->finishReading($userId, $publicationId, $format);
        return $this->jsonEmpty();
    }

    public function reset(HttpContext $context, string $id)
    {
        $publicationId = RouteParamParser::positiveInt(['id' => $id], 'id');
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $format = QueryParser::optionalEnum($context->request->body(), 'format', BookFormat::class);
        $this->service->resetProgress($userId, $publicationId, $format);
        return $this->jsonEmpty();
    }

    public function publish(HttpContext $context, string $id) 
    {
        $publicationId = RouteParamParser::positiveInt(['id' => $id], 'id');
        $this->service->publish($publicationId);
        return $this->jsonEmpty();
    }

    public function getAuthors(HttpContext $context, string $id)
    {
        $publicationId = RouteParamParser::positiveInt(['id' => $id], 'id');
        
        $query = RolesUserListQuery::fromCustomRole($context->request->query, AUTHOR_ROLE_ID);
        $list = $this->permsService->getUsers($publicationId, $query);
        return $this->jsonList($list->getArray(), [
            'page' => $list->getPage(),
            'pageSize' => $list->getPageSize(),
            'hasNext' => $list->hasNext()
        ]);
    }

    public function delete(HttpContext $context, string $id)
    {
        $publicationId = RouteParamParser::positiveInt(['id' => $id], 'id');

        $this->service->delete($publicationId);
        return $this->jsonEmpty(200);
    }
}