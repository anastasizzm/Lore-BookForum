<?php
declare(strict_types=1);

namespace App\Controllers\Api\System;

use App\Http\HttpContext;
use App\Http\Response;
use App\Http\HttpException;
use App\Lib\I18n\Translator;

use App\Controllers\Controller;

use App\Models\Error;
use App\Models\Enums\ServiceType;
use App\Models\Queries\Permissions\RolesUserListQuery;
use App\Models\Queries\PaginationQuery;
use App\Repositories\RuleProviders\Common\CommonRuleProvider;
use App\Attributes\Inject;

use App\Constants;
use App\ErrorCodes;
use App\Forms\Permissions\SystemPermissionForm;
use App\Forms\Permissions\PositionForm;
use App\Forms\Permissions\EditPermissionForm;
use App\Forms\Assignments\RoleUserAssignmentForm;
use App\Forms\Assignments\RoleToUserAssignmentForm;
use App\Services\Permissions\SystemPermissionsService;
use App\Extensions\Parsers\RouteParamParser;
use App\Exceptions\ForbiddenException;

final class SystemPermissionsController extends Controller
{
    public function __construct(
        #[Inject(key: ServiceType::System)]
        private readonly CommonRuleProvider $ruleProvider,
        private readonly SystemPermissionsService $permissionsService,
        private readonly Translator $translator
    ){}

    public function getCommonRules(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $rules = $this->ruleProvider->getRule($userId);
        return $this->jsonObject($rules);
    }

    public function getRoles(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $items = $this->permissionsService->getRolesWithContext($userId);
        return $this->jsonList($items);
    }

    public function addRole(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $form = SystemPermissionForm::fromInput($context->request->body());
        if ($form->belowId !== NULL && !$this->permissionsService->canManageRole($userId, $form->belowId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $version = $this->permissionsService->addRole($form);
        return $this->jsonEmpty(201)->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function editRole(HttpContext $context, string $roleId) 
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $roleId = RouteParamParser::positiveInt(['r' => $roleId], 'r');
        $form = EditPermissionForm::fromInput($context->request->body());
        $expectedVersion = $this->requireVersion($context);

        if (!$this->permissionsService->canManageRole($userId, $roleId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $version = $this->permissionsService->editRole($roleId, $form, $expectedVersion);
        return $this->jsonEmpty()->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function moveRole(HttpContext $context, string $roleId)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $roleId = RouteParamParser::positiveInt(['r' => $roleId], 'r');
        $expectedVersion = $this->requireVersion($context);
        $form = PositionForm::fromInput($context->request->body());

        if (!$this->permissionsService->canManageRole($userId, $roleId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $newVersion = $this->permissionsService->moveRole(
            roleId: $roleId,
            form: $form,
            expectedVersion: $expectedVersion,
        );

        return $this->jsonEmpty()->withHeader('ETag', sprintf('"%d"', $newVersion));
    }

    public function removeRole(HttpContext $context, string $roleId)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $roleId = RouteParamParser::positiveInt(['r' => $roleId], 'r');
        if (!$this->permissionsService->canManageRole($userId, $roleId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $this->permissionsService->removeRole($roleId);
        return $this->jsonEmpty(204);
    }

    public function getUsers(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $query = RolesUserListQuery::fromInput($context->request->query);
        $list = $this->permissionsService->getUsersWithContext($query, $userId);
        return $this->jsonList($list->getArray(), [
            'page' => $list->getPage(),
            'pageSize' => $list->getPageSize(),
            'hasNext' => $list->hasNext()
        ]);
    }

    public function addUser(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $form = RoleUserAssignmentForm::fromInput($context->request->body());

        if (!$this->permissionsService->canManageRole($userId, $form->roleId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $version = $this->permissionsService->addUser($form->userId, $form, $userId);
        return $this->jsonEmpty(201)->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function getUserRoles(HttpContext $context, string $userId)
    {
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $pageQ = PaginationQuery::fromInput($context->request->query);

        $list = $this->permissionsService->getUserRoles($userId, $pageQ);
        return $this->jsonList($list->getArray(), [
            'page' => $list->getPage(),
            'pageSize' => $list->getPageSize(),
            'hasNext' => $list->hasNext()
        ]);
    }

    public function addRoleToUser(HttpContext $context, string $userId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $form = RoleToUserAssignmentForm::fromInput($context->request->body());

        if (!$this->permissionsService->canManageUser($currentUserId, $userId)
            || !$this->permissionsService->canManageRole($currentUserId, $form->roleId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $version = $this->permissionsService->addRoleToUser($userId, $form, $currentUserId);
        return $this->jsonEmpty(201)->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function removeUser(HttpContext $context, string $userId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $roleId = RouteParamParser::optionalPositiveInt(['r' => $roleId], 'r');

        if (!$this->permissionsService->canManageUser($currentUserId, $userId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $this->permissionsService->removeUser($userId);
        return $this->jsonEmpty(204);
    }

    public function removeUserRole(HttpContext $context, string $userId, string $roleId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $roleId = RouteParamParser::optionalPositiveInt(['r' => $roleId], 'r');

        if (!$this->permissionsService->canManageUser($currentUserId, $userId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role"), ErrorCodes::FORBIDDEN);

        $newVersion = $this->permissionsService->removeUserRole($userId, $roleId);

        return $this->jsonEmpty(204)->withHeader('ETag', sprintf('"%d"', $newVersion));
    }

    /**
     * Разбирает If-Match: '"5"' → 5.
     * 428 Precondition Required — если заголовка нет,
     * 400 Bad Request — если он есть, но не число.
     */
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
}