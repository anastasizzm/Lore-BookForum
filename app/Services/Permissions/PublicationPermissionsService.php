<?php
declare(strict_types=1);

namespace App\Services\Permissions;

use App\Repositories\Permissions\PublicationPermissionsRepository;
use App\Exceptions\NotFoundException;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Models\PaginatedList;
use App\Models\Access\Assignment\RolesUser;
use App\Models\Queries\PaginationQuery;
use App\Models\Enums\UsersSortBy;
use App\Models\Queries\Permissions\RolesUserListQuery;
use App\Exceptions\Translators\PermissionExceptionTranslator;
use App\Extensions\EnumExtensions;
use App\Forms\Permissions\PublicationPermissionForm;
use App\Forms\Permissions\PositionForm;
use App\Forms\Permissions\EditPublicationPermissionForm;
use App\Forms\Assignments\RoleUserAssignmentForm;
use App\Forms\Assignments\RoleToUserAssignmentForm;
use App\Lib\I18n\Translator;
use App\Services\Configuration\UnitOfWork;
use App\Services\Enrichers\RolesPermissionsContextEnricher;
use App\Services\Enrichers\UsersPermissionsContextEnricher;
use App\Services\Permissions\Ranks\PublicationRoleRankService;
use App\Lib\LexoRank;
use PDO;

final class PublicationPermissionsService
{
    private const MOVE_MAX_ATTEMPTS = 3;

    private readonly UsersPermissionsContextEnricher $usersContextEnricher;
    private readonly RolesPermissionsContextEnricher $rolesContextEnricher;
    
    public function __construct(
        private readonly UnitOfWork $uow,
        private readonly PublicationRoleRankService $roleRankService,
        private readonly PublicationPermissionsRepository $repository,
        private readonly PermissionExceptionTranslator $exceptionTranslator,
        private readonly Translator $translator
    ) {
        $this->rolesContextEnricher = new RolesPermissionsContextEnricher(
            $roleRankService,
            $repository
        );
        $this->usersContextEnricher = new UsersPermissionsContextEnricher(
            $roleRankService
        );
    }

    public function getRoles(int $publicationId) : array
    {
        return $this->repository->getRoles($publicationId);
    }

    public function getRolesWithContext(int $publicationId, int $viewerId) : array 
    {
        $items = $this->getRoles($publicationId);
        return $this->rolesContextEnricher->enrich($items, $viewerId, $publicationId);
    }

    public function addRole(int $publicationId, PublicationPermissionForm $permissions): int
    {
        try {
            return $this->uow->transactional(function () use ($permissions, $publicationId) {
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
        EditPublicationPermissionForm $form,
        int $expectedVersion
    ): int {
        if ($form->pubPermissions === NULL)
            throw new ValidationException(['pubPermissions' => $this->translator->t("errors.common.required")]);

        try {
            return $this->uow->transactional(function () use ($publicationId, $roleId, $form, $expectedVersion) {
                $newVersion = $this->repository->editRole(
                    $roleId,
                    $publicationId,
                    $expectedVersion,
                    $form->pubPermissions
                );

                if ($newVersion === null) {
                    throw new ConflictException($this->translator->t('errors.common.version_invalid'));
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
                return $this->uow->transactional(function () use (
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
                        } catch (\RuntimeException) {
                            throw new ConflictException($this->translator->t('errors.roles.position_changed'));
                        }
                    }
                    $nudged = $candidate;

                    $newVersion = $this->repository->editLexorank(
                        $roleId, $publicationId, $candidate, $expectedVersion
                    );

                    if ($newVersion === null) {
                        throw new ConflictException($this->translator->t('errors.common.version_invalid'));
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

    public function getUsers(int $publicationId, RolesUserListQuery $query) : PaginatedList 
    {
        $errors = [];
        $isValid = $query->properties->validateForType(RolesUser::class, $errors);
        if(!$isValid) throw new ValidationException($errors);

        $sortEnum = $query->sort->hasData() 
            ? EnumExtensions::tryResolve(UsersSortBy::class, $query->sort->sortString()) 
            : UsersSortBy::Username;

        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();

        $items = $this->repository->getUsers($publicationId, $sortEnum, $query->roleId, $page, $pageSize, $query->properties->getProps(), RolesUser::class);
        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function getUsersWithContext(int $publicationId, RolesUserListQuery $query, int $viewerId) : PaginatedList 
    {
        $items = $this->getUsers($publicationId, $query);
        $enriched = $this->usersContextEnricher->enrich($items->getArray(), $viewerId, $publicationId);

        return PaginatedList::fromArray($enriched, $items->getPage(), $items->getPageSize());
    }

    public function addUser(int $publicationId, int $userId, RoleToUserAssignmentForm $form, int $granterId): int
    {
        try {
            return $this->uow->transactional(function () use ($publicationId, $userId, $form, $granterId) : int {
                $rolesCount = $this->repository->countUserRoles($publicationId, $userId);
                if ($rolesCount !== 0)
                    throw new ConflictException($this->translator->t('item_based.exists.m', [':item' => $this->translator->t('display_names.user')]));

                return $this->repository->addUser(
                    $userId,
                    $form->roleId,
                    $publicationId,
                    $granterId
                );
            });
        } catch (\PDOException $e) {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function removeUser(int $publicationId, int $userId): void
    {
        $removed = $this->repository->removeUser($userId, null, $publicationId);
        if (!$removed) {
            throw new NotFoundException($this->translator->t('errors.common.not_found'));
        }
    }

    public function getUserRoles(int $publicationId, int $userId, PaginationQuery $pagination) : PaginatedList 
    {
        $page = $pagination->page();
        $pageSize = $pagination->pageSize();
        $items = $this->repository->getUserRoles($publicationId, $userId, $page, $pageSize);

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function addRoleToUser(int $publicationId, int $userId, RoleToUserAssignmentForm $form, int $granterId): int
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

    public function removeUserRole(int $publicationId, int $userId, int $roleId): void
    {
        $this->uow->transactional(function () use ($userId, $roleId, $publicationId) {
            $rolesCount = $this->repository->countUserRoles($publicationId, $userId);
            if ($rolesCount < 2)
                throw new ConflictException($this->translator->t('errors.roles.last_role'));
        
            $removed = $this->repository->removeUser($userId, $roleId, $publicationId);
            if (!$removed) {
                throw new NotFoundException($this->translator->t('errors.common.not_found'));
            }
        });
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