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

    public function addRole(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $form = SystemPermissionForm::fromInput($context->request->body());
        if ($form->belowId !== NULL && !$this->permissionsService->canManageRole($userId, $form->belowId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

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
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

        $version = $this->permissionsService->moveRole($roleId, $form, $expectedVersion);
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
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

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
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

        $this->permissionsService->removeRole($roleId);
        return $this->jsonEmpty(204);
    }

    public function addUser(HttpContext $context)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $form = RoleUserAssignmentForm::fromInput($context->request->body());

        if (!$this->permissionsService->canManageRole($userId, $form->roleId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

        $version = $this->permissionsService->addUser($form->userId, $form, $granterId);
        return $this->jsomEmpty(201)->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function addRoleToUser(HttpContext $context, string $userId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $form = RoleToUserAssignmentForm::fromInput($context->request->body());

        if (!$this->permissionsService->canManageUser($userId, $currentUserId)
            || !$this->permissionsService->canManageRole($userId, $form->roleId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

        $version = $this->permissionsService->addUser($userId, $form, $granterId);
        return $this->jsomEmpty(201)->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function removeUser(HttpContext $context, string $userId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $roleId = RouteParamParser::optionalPositiveInt(['r' => $roleId], 'r');

        if (!$this->permissionsService->canManageUser($userId, $userId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

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

        if (!$this->permissionsService->canManageUser($userId, $userId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

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
            throw new HttpException(428, 'If-Match header is required');
        }
        $stripped = trim($ifMatch, '"');
        if (!ctype_digit($stripped)) {
            throw new HttpException(400, 'Invalid If-Match header');
        }
        return (int)$stripped;
    }
}