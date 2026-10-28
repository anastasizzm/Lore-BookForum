<?php
declare(strict_types=1);

namespace App\Services\Permissions;

use App\Repositories\Permissions\SystemPermissionsRepository;
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
use App\Forms\Permissions\SystemPermissionForm;
use App\Forms\Permissions\PositionForm;
use App\Forms\Permissions\EditPermissionForm;
use App\Forms\Assignments\RoleUserAssignmentForm;
use App\Forms\Assignments\RoleToUserAssignmentForm;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;
use App\Services\Configuration\UnitOfWork;
use App\Services\Permissions\Ranks\SystemRoleRankService;
use App\Services\Enrichers\RolesPermissionsContextEnricher;
use App\Services\Enrichers\UsersPermissionsContextEnricher;
use App\Lib\LexoRank;
use PDO;

final class SystemPermissionsService
{
    private const MOVE_MAX_ATTEMPTS = 3;

    private readonly UsersPermissionsContextEnricher $usersContextEnricher;
    private readonly RolesPermissionsContextEnricher $rolesContextEnricher;

    public function __construct(
        private readonly UnitOfWork $uow,
        private readonly SystemRoleRankService $roleRankService,
        private readonly SystemPermissionsRepository $repository,
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

    public function getRoles() : array
    {
        return $this->repository->getRoles();
    }

    public function getRolesWithContext(int $viewerId) : array 
    {
        $items = $this->getRoles();
        return $this->rolesContextEnricher->enrich($items, $viewerId);
    }

    public function addRole(SystemPermissionForm $permissions): int
    {
        try {
            return $this->uow->transactional(function (PDO $pdo) use ($permissions) {
                $lexorank = $this->roleRankService->computeInsertRank(
                    null,
                    $permissions->aboveId,
                    $permissions->belowId
                );

                return $this->repository->addRole(
                    $permissions->roleId,
                    null,
                    $lexorank,
                    $permissions->pubPermissions,
                    $permissions->postPermissions,
                    $permissions->systemPermissions,
                );
            });
        } catch (\PDOException $e) {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function editRole(int $roleId, EditPermissionForm $form, int $expectedVersion): int
    {
        $bag = new ErrorBag();
        if ($form->pubPermissions === NULL)
            $bag->add('pubPermissions', [$this->translator->t("errors.common.required")]);
           
        if ($form->systemPermissions === NULL)
            $bag->add('systemPermissions', [$this->translator->t("errors.common.required")]);

        if ($form->postPermissions === NULL)
            $bag->add('postPermissions', [$this->translator->t("errors.common.required")]);

        if (!$bag->isEmpty())
            throw new ValidationException($bag->all());

        try {
            return $this->uow->transactional(function () use ($roleId, $form, $expectedVersion) {
                $newVersion = $this->repository->editRole(
                    $roleId,
                    null,
                    $expectedVersion,
                    $form->pubPermissions,
                    $form->postPermissions,
                    $form->systemPermissions,
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
        int $roleId,
        PositionForm $form,
        int $expectedVersion
    ): int {
        $nudged = null;
        $lastException = null;
        $aboveId = $form->aboveId;
        $belowId = $form->belowId;

        for ($attempt = 1; $attempt <= self::MOVE_MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->uow->transactional(function () use (
                    &$nudged, $roleId, $aboveId, $belowId, $expectedVersion
                ) {
                    if ($nudged === null) {
                        $candidate = $this->roleRankService->computeInsertRank(
                            null, $aboveId, $belowId, $roleId
                        );
                    } else {
                        $aboveRank = $aboveId !== null
                            ? $this->repository->getRank($aboveId, null)
                            : null;
                        try {
                            $candidate = LexoRank::between($nudged, $aboveRank);
                        } catch (\RuntimeException) {
                            throw new ConflictException($this->translator->t('errors.roles.position_changed'));
                        }
                    }
                    $nudged = $candidate;

                    $newVersion = $this->repository->editLexorank(
                        $roleId, null, $candidate, $expectedVersion
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
                $lastException = $e;
            }
        }

        throw new ConflictException($this->translator->t("errors.roles.position_taken"));
    }

    public function removeRole(int $roleId): void
    {
        try {
            $this->repository->removeRole($roleId, null);
        } catch (\PDOException $e) {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function getUsers(RolesUserListQuery $query) : PaginatedList 
    {
        $errors = [];
        $isValid = $query->properties->validateForType(RolesUser::class, $errors);
        if(!$isValid) throw new ValidationException($errors);

        $sortEnum = $query->sort->hasData() 
            ? EnumExtensions::tryResolve(UsersSortBy::class, $query->sort->sortString()) 
            : UsersSortBy::Username;

        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();

        $items = $this->repository->getUsers(NULL, $sortEnum, $page, $pageSize, $query->properties->getProps());
        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function getUsersWithContext(RolesUserListQuery $query, int $viewerId) : PaginatedList 
    {
        $items = $this->getUsers($query);
        $enriched = $this->usersContextEnricher->enrich($items->getArray(), $viewerId);

        return PaginatedList::fromArray($enriched, $items->getPage(), $items->getPageSize());
    }

    public function addUser(int $userId, RoleToUserAssignmentForm $form, int $granterId): int
    {
        try {
            return $this->uow->transactional(function () use ($userId, $form, $granterId) : int {
                $rolesCount = $this->repository->countUserRoles(null, $userId);
                if ($rolesCount !== 0)
                    throw new ConflictException($this->translator->t('item_based.exists.m', [':item' => $this->translator->t('display_names.user')]));

                return $this->repository->addUser(
                    $userId,
                    $form->roleId,
                    null,
                    $granterId
                );
            });
        } catch (\PDOException $e) {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function removeUser(int $userId): void
    {
        $removed = $this->repository->removeUser($userId, null, null);
        if (!$removed) {
            throw new NotFoundException($this->translator->t('errors.common.not_found'));
        }
    }

    public function getUserRoles(int $userId, PaginationQuery $pagination) : PaginatedList 
    {
        $page = $pagination->page();
        $pageSize = $pagination->pageSize();
        $items = $this->repository->getUserRoles(NULL, $userId, $page, $pageSize);

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function addRoleToUser(int $userId, RoleToUserAssignmentForm $form, int $granterId): int
    {
        try {
            return $this->repository->addUser(
                $userId,
                $form->roleId,
                null,
                $granterId
            );
        } catch (\PDOException $e) {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function removeUserRole(int $userId, int $roleId): void
    {
        $this->uow->transactional(function () use ($userId, $roleId) {
            $rolesCount = $this->repository->countUserRoles(NULL, $userId);
            if ($rolesCount < 2)
                throw new ConflictException($this->translator->t('errors.roles.last_role'));
        
            $removed = $this->repository->removeUser($userId, $roleId, null);
            if (!$removed) {
                throw new NotFoundException($this->translator->t('errors.common.not_found'));
            }
        });
    }

    public function canManageUser(int $userId, int $targetUserId): bool
    {
        return $this->roleRankService->canManageUser($userId, $targetUserId, null);
    }

    public function canManageRole(int $userId, int $roleId): bool
    {
        return $this->roleRankService->canManageRole($userId, $roleId, null);
    }
}