<?php
declare(strict_types=1);

namespace App\Controllers\Api\Publication;

use App\Http\HttpContext;
use App\Http\HttpException;
use App\Lib\I18n\Translator;

use App\Controllers\Controller;

use App\Models\Error;
use App\Exceptions\ForbiddenException;
use App\Attributes\Inject;

use App\Constants;
use App\ErrorCodes;
use App\Forms\Permissions\PublicationPermissionForm;
use App\Forms\Permissions\PositionForm;
use App\Forms\Permissions\EditPermissionForm;
use App\Forms\Assignments\RoleUserAssignmentForm;
use App\Services\Permissions\PublicationPermissionsService;
use App\Extensions\Parsers\RouteParamParser;

final class PublicationPermissionsController extends Controller
{
    public function __construct(
        private readonly PublicationPermissionsService $permissionsService,
        private readonly Translator $translator
    ){}

    public function addRole(HttpContext $context, string $publicationId)
    {
        $userId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($userId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $form = PublicationPermissionForm::fromInput($context->request->body());

        if ($form->belowId !== NULL
            && !$this->permissionsService->canManageRole($userId, $form->belowId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

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
        $form = EditPermissionForm::fromInput($context->request->body());
        $expectedVersion = $this->requireVersion($context);

        if (!$this->permissionsService->canManageRole($userId, $roleId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

        $version = $this->permissionsService->moveRole($publicationId, $roleId, $form, $expectedVersion);
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
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

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
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

        $this->permissionsService->removeRole($publicationId, $roleId);
        return $this->jsonEmpty(204);
    }

    public function addUser(HttpContext $context, string $publicationId)
    {
        $granterId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($granterId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $form = RoleUserAssignmentForm::fromInput($context->request->body());

        if (!$this->permissionsService->canManageRole($granterId, $form->roleId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

        $version = $this->permissionsService->addUser($publicationId, $form->userId, $form, $granterId);
        return $this->jsonEmpty(201)->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function addRoleToUser(HttpContext $context, string $publicationId, string $userId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $userId = RouteParamParser::positiveInt(['u' => $userId], 'u');
        $form = RoleToUserAssignmentForm::fromInput($context->request->body());

        if (!$this->permissionsService->canManageUser($userId, $currentUserId, $publicationId)
            || !$this->permissionsService->canManageRole($userId, $form->roleId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

        $version = $this->permissionsService->addUser($publicationId, $userId, $form, $granterId);
        return $this->jsomEmpty(201)->withHeader('ETag', sprintf('"%d"', $version));
    }

    public function removeUser(HttpContext $context, string $publicationId, string $userId)
    {
        $currentUserId = $context->attribute(Constants::USER_ID_ATTR);
        if (empty($currentUserId))
            return $this->jsonError(new Error(ErrorCodes::UNAUTHORIZED, "Authorize first"), 401);

        $publicationId = RouteParamParser::positiveInt(['p' => $publicationId], 'p');
        $targetUserId  = RouteParamParser::positiveInt(['u' => $userId], 'u');

        if (!$this->permissionsService->canManageUser($currentUserId, $targetUserId, $publicationId))
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

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
            throw new ForbiddenException($this->translator->t("errors.roles.insufficient_role", ErrorCodes::FORBIDDEN));

        $newVersion = $this->permissionsService->removeUserRole($publicationId, $targetUserId, $roleId);

        return $this->jsonEmpty(204)->withHeader('ETag', sprintf('"%d"', $newVersion));
    }

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