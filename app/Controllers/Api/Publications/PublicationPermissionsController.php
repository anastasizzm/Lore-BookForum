<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publications;

use App\Http\Response;
use App\Http\HttpContext;
use App\Http\HttpException;
use App\Lib\I18n\Translator;

use App\Controllers\Controller;

use App\Models\Error;
use App\Models\Enums\ServiceType;
use App\Models\Queries\PaginationQuery;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Models\Queries\Permissions\RolesUserListQuery;
use App\Attributes\Inject;

use App\Constants;
use App\ErrorCodes;
use App\Forms\Permissions\PublicationPermissionForm;
use App\Forms\Permissions\PositionForm;
use App\Forms\Permissions\EditPublicationPermissionForm;
use App\Forms\Assignments\RoleUserAssignmentForm;
use App\Forms\Assignments\RoleToUserAssignmentForm;
use App\Services\Permissions\PublicationPermissionsService;
use App\Extensions\Parsers\RouteParamParser;
use App\Exceptions\ForbiddenException;

final class PublicationPermissionsController extends Controller
{
    public function __construct(
        #[Inject(key: ServiceType::Publication)]
        private readonly CommonRuleProvider $ruleProvider,
        private readonly PublicationPermissionsService $permissionsService,
        private readonly Translator $translator
    ){}

    public function getRoles(HttpContext $context, string $publicationId)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');

        $items = $this->permissionsService->getRolesWithContext($publicationId, $userId);
        return $this->jsonList($items);
    }

    public function addRole(HttpContext $context, string $publicationId)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $form = PublicationPermissionForm::fromInput($context->request->body());

        if ($form->belowId !== NULL
            && !$this->permissionsService->canManageRole($userId, $form->belowId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $version = $this->permissionsService->addRole($publicationId, $form);
        return $this->jsonEmpty(201)->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function editRole(HttpContext $context, string $publicationId, string $roleId) 
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $roleId = RouteParamParser::positiveInt(['r' => $roleId], 'r');
        $form = EditPublicationPermissionForm::fromInput($context->request->body());
        $expectedVersion = $this->requireVersion($context);

        if (!$this->permissionsService->canManageRole($userId, $roleId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $version = $this->permissionsService->editRole($publicationId, $roleId, $form, $expectedVersion);
        return $this->jsonEmpty()->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function moveRole(HttpContext $context, string $publicationId, string $roleId)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId   = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $roleId          = RouteParamParser::positiveInt(['r' => $roleId], 'r');
        $expectedVersion = $this->requireVersion($context);
        $form            = PositionForm::fromInput($context->request->body());

        if (!$this->permissionsService->canManageRole($userId, $roleId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $newVersion = $this->permissionsService->moveRole(
            publicationId:   $publicationId,
            roleId:          $roleId,
            form:            $form,
            expectedVersion: $expectedVersion,
        );

        return $this->jsonEmpty()->withHeader('ETag', sprintf('"%d"', $newVersion));
    }

    public function removeRole(HttpContext $context, string $publicationId, string $roleId)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $roleId        = RouteParamParser::positiveInt(['r' => $roleId], 'r');

        if (!$this->permissionsService->canManageRole($userId, $roleId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $this->permissionsService->removeRole($publicationId, $roleId);
        return $this->jsonEmpty(204);
    }

    public function getUsers(HttpContext $context, string $publicationId)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');

        $query = RolesUserListQuery::fromInput($context->request->query);
        $list = $this->permissionsService->getUsersWithContext($publicationId, $query, $userId);
        return $this->jsonList($list->getArray(), [
            'page' => $list->getPage(),
            'pageSize' => $list->getPageSize(),
            'hasNext' => $list->hasNext()
        ]);
    }

    public function addUser(HttpContext $context, string $publicationId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $form = RoleUserAssignmentForm::fromInput($context->request->body());

        if (!$this->permissionsService->canManageRole($currentUserId, $form->roleId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $version = $this->permissionsService->addUser($publicationId, $form->userId, $form, $currentUserId);
        return $this->jsonEmpty(201)->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function getUserRoles(HttpContext $context, string $publicationId, string $userId)
    {
        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $pageQ = PaginationQuery::fromInput($context->request->query);

        $list = $this->permissionsService->getUserRoles($publicationId, $userId, $pageQ);
        return $this->jsonList($list->getArray(), [
            'page' => $list->getPage(),
            'pageSize' => $list->getPageSize(),
            'hasNext' => $list->hasNext()
        ]);
    }

    public function addRoleToUser(HttpContext $context, string $publicationId, string $userId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $form = RoleToUserAssignmentForm::fromInput($context->request->body());

        if (!$this->permissionsService->canManageUser($currentUserId, $userId, $publicationId)
            || !$this->permissionsService->canManageRole($currentUserId, $form->roleId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $version = $this->permissionsService->addRoleToUser($publicationId, $userId, $form, $currentUserId);
        return $this->jsonEmpty(201)->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function removeUser(HttpContext $context, string $publicationId, string $userId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $targetUserId  = RouteParamParser::positiveInt(['u' => $userId], 'u');

        if (!$this->permissionsService->canManageUser($currentUserId, $targetUserId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $this->permissionsService->removeUser($publicationId, $targetUserId);
        return $this->jsonEmpty(204);
    }

    public function removeUserRole(HttpContext $context, string $publicationId, string $userId, string $roleId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $targetUserId  = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $roleId        = RouteParamParser::positiveInt(['r' => $roleId], 'r');

        if (!$this->permissionsService->canManageUser($currentUserId, $targetUserId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $this->permissionsService->removeUserRole($publicationId, $targetUserId, $roleId);
        return $this->jsonEmpty(204);
    }

    private function requireVersion(HttpContext $context): int
    {
        $ifMatch = $context->header('If-Match');
        if ($ifMatch === null || $ifMatch === '') {
            throw new HttpException('If-Match header is required', 428, ErrorCodes::INVALID_HEADER);
        }
        $stripped = trim($ifMatch, '"');
        if (!ctype_digit($stripped)) {
            throw new HttpException('Invalid If-Match header', 400, ErrorCodes::INVALID_HEADER);
        }
        return (int)$stripped;
    }

    public function getCommonRules(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $rules = $this->ruleProvider->getRule($userId);
        return $this->jsonObject($rules);
    }
}