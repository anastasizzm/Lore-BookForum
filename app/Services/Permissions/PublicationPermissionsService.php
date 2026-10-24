<?php
declare(strict_types=1);

namespace App\Services\Permissions;

use App\Repositories\Permissions\PermissionsRepository;
use App\Exceptions\NotFoundException;
use App\Exceptions\ConflictException;
use App\Exceptions\Translator\PermissionExceptionTranslator;
use App\Forms\Permissions\PublicationPermissionForm;
use App\Forms\Permissions\PositionForm;
use App\Forms\Permissions\EditPermissionForm;
use App\Forms\Assignments\RoleUserAssignmentForm;
use App\Forms\Assignments\RoleToRoleUserAssignmentForm;
use App\Lib\I18n\Translator;
use App\Services\Configuration\UnitOfWork;
use App\Services\LexoRank\LexoRank;
use PDO;

final class PublicationPermissionsService
{
    private const MOVE_MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly RoleRankService $roleRankService,
        private readonly UnitOfWork $uow,
        private readonly PermissionsRepository $repository,
        private readonly PermissionExceptionTranslator $exceptionTranslator,
        private readonly Translator $translator
    ) {}

    public function addRole(int $publicationId, PublicationPermissionForm $permissions): int
    {
        try {
            return $this->uow->translactional(function () use ($permissions, $publicationId) {
                $lexorank = $this->roleRankService->computeInsertRank(
                    $publicationId,
                    $permissions->aboveId,
                    $permissions->belowId
                );

                return $this->repository->addRole(
                    $permissions->roleId,
                    $publicationId,
                    $lexorank,
                    $permissions->pubPermissions
                );
            });
        } catch (\PDOException $e) {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function editRole(
        int $publicationId,
        int $roleId,
        EditPermissionForm $form,
        int $expectedVersion
    ): int {
        try {
            return $this->uow->translactional(function () use ($publicationId, $roleId, $form, $expectedVersion) {
                $newVersion = $this->repository->editRole(
                    $roleId,
                    $publicationId,
                    $expectedVersion,
                    $form->pubPermissions
                );

                if ($newVersion === null) {
                    throw new ConflictException($this->translator-t('errors.common.version_invalid'));
                }

                return $newVersion;
            });
        } catch (\PDOException $e) {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function moveRole(
        int $publicationId,
        int $roleId,
        PositionForm $form,
        int $expectedVersion
    ): int {
        $aboveId = $form->aboveId;
        $belowId = $form->belowId;
        $nudged = null;

        for ($attempt = 1; $attempt <= self::MOVE_MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->uow->translactional(function () use (
                    &$nudged, $publicationId, $roleId, $aboveId, $belowId, $expectedVersion
                ) {
                    if ($nudged === null) {
                        $candidate = $this->roleRankService->computeInsertRank(
                            $publicationId, $aboveId, $belowId, $roleId
                        );
                    } else {
                        $aboveRank = $aboveId !== null
                            ? $this->repository->getRank($aboveId, $publicationId)
                            : null;
                        try {
                            $candidate = LexoRank::between($nudged, $aboveRank);
                        } catch (\LogicException) {
                            throw new ConflictException($this->translator->t('errors.roles.position_changed'));
                        }
                    }
                    $nudged = $candidate;

                    $newVersion = $this->repository->editLexorank(
                        $roleId, $publicationId, $candidate, $expectedVersion
                    );

                    if ($newVersion === null) {
                        throw new ConflictException($this->translator-t('errors.common.version_invalid'));
                    }

                    return $newVersion;
                });
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23505') {
                    throw $this->exceptionTranslator->translate($e);
                }
            }
        }

        throw new ConflictException($this->translator->t("errors.roles.position_taken"));
    }

    public function removeRole(int $publicationId, int $roleId): void
    {
        try {
            $this->repository->removeRole($roleId, $publicationId);
        } catch (\PDOException $e) {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function addUser(int $publicationId, int $userId, RoleToUserAssignmentForm $form, int $granterId): int
    {
        try {
            return $this->repository->addUser(
                $userId,
                $form->roleId,
                $publicationId,
                $granterId
            );
        } catch (\PDOException $e) {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function removeUser(int $publicationId, int $userId): void
    {
        $versions = $this->repository->removeUser($userId, null, $publicationId);
        if ($versions === []) {
            throw new NotFoundException($this->translator->t('errors.common.not_found'));
        }
    }

    public function removeUserRole(int $publicationId, int $userId, int $roleId): int
    {
        $versions = $this->repository->removeUser($userId, $roleId, $publicationId);
        if ($versions === []) {
            throw new NotFoundException($this->translator->t('errors.common.not_found'));
        }
        return $versions[0];
    }

    public function canManageUser(int $userId, int $targetUserId, int $publicationId): bool
    {
        return $this->roleRankService->canManageUser($userId, $targetUserId, $publicationId);
    }

    public function canManageRole(int $userId, int $roleId, int $publicationId): bool
    {
        return $this->roleRankService->canManageRole($userId, $roleId, $publicationId);
    }
}