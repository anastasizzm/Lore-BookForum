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
use App\Exceptions\EnumExtensions;
use App\Forms\Permissions\PublicationPermissionForm;
use App\Forms\Permissions\PositionForm;
use App\Forms\Permissions\EditPermissionForm;
use App\Forms\Assignments\RoleUserAssignmentForm;
use App\Forms\Assignments\RoleToRoleUserAssignmentForm;
use App\Lib\I18n\Translator;
use App\Services\Configuration\UnitOfWork;
use App\Services\Enrichers\RolesPermissionsContextEnricher;
use App\Services\Enrichers\UsersPermissionsContextEnricher;
use App\Services\Permissions\Ranks\PublicationRoleRankService;
use App\Services\LexoRank\LexoRank;
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
        EditPermissionForm $form,
        int $expectedVersion
    ): int {
        try {
            return $this->uow->transactional(function () use ($publicationId, $roleId, $form, $expectedVersion) {
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

        $items = $this->repository->getUsers($publicationId, $sortEnum, $page, $pageSize, $query->properties->getProps());
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

    public function getUserRoles(int $publicationId, int $userId, PaginationQuery $pagination) : PaginatedList 
    {
        $page = $pagination->page();
        $pageSize = $pagination->pageSize();
        $items = $this->repository->getUserRoles($publicationId, $userId, $page, $pageSize);

        return PaginatedList::fromArray($items, $page, $pageSize);
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