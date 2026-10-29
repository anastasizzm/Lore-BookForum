<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\RolesRepository;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\UserRoleForm;
use App\Models\Additional\UserRole;
use App\Validators\Additionals\UserRoleFormValidator;
use App\Lib\Settings\Settings;
use App\Lib\I18n\Translator;
use App\Http\HttpException;
use App\Extensions\EnumExtensions;
use App\Models\PaginatedList;
use App\Models\Enums\BasicModelSortBy;
use App\Models\Enums\RoleStatus;
use App\Exceptions\Translators\RolesExceptionTranslator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ConflictException;
use App\Models\Errors\ErrorBag;
use App\ErrorCodes;
use PDOException;

final class RolesService
{
    public function __construct(
        private readonly RolesRepository $rolesRepo,
        private readonly UserRoleFormValidator $validator,
        private readonly RolesExceptionTranslator $translator,
        private readonly Settings $settings,
        private readonly Translator $i18n
    ){}

    public function getList(BasicModelListQuery $query) : PaginatedList
    {
        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();

        $sortEnum = ($query->sort->hasData()
            ? EnumExtensions::tryResolve(BasicModelSortBy::class, $query->sort->sortString())
            : null) ?? BasicModelSortBy::Alphabet;

        $statusEnum = ($query->status->hasData()
            ? EnumExtensions::tryResolve(RoleStatus::class, $query->sort->sortString())
            : null) ?? RoleStatus::All;


        $items = $this->rolesRepo->getList(
            $page, 
            $pageSize, 
            $sortEnum,
            $query->search,
            $statusEnum
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function retrieve(int $roleId) : UserRole 
    {
        $model = $this->rolesRepo->retrieve($roleId);
        if ($model === NULL) throw new NotFoundException($this->i18n->t('errors.common.not_found'), ErrorCodes::NOT_FOUND);
        return $model;
    }

    public function create(UserRoleForm $form) : int
    {
        $bag = new ErrorBag();
        $this->requireDefaultLanguage($form->translations, $bag);
        $this->validator->validate($form, $bag);

        if (!$bag->isEmpty()) {
            throw new ValidationException($bag->all());
        }

        try {
            return $this->rolesRepo->create($form->color, $form->isPublic, $form->translations);
        } catch (PDOException $e) {
            throw $this->translator->translate($e);
        }
    }

    public function update(int $id, UserRoleForm $form) : void
    {
        $bag = new ErrorBag();
        $this->requireDefaultLanguage($form->translations, $bag);
        $this->validator->validate($form, $bag);

        if (!$bag->isEmpty()) {
            throw new ValidationException($bag->all());
        }

        try {
            $exists = $this->rolesRepo->update($id, $form->color, $form->isPublic, $form->translations);
            if (!$exists) {
                throw new NotFoundException($this->i18n->t('errors.common.not_found'), ErrorCodes::NOT_FOUND);
            }
        } catch (PDOException $e) {
            throw $this->translator->translate($e);
        }
    }

    public function delete(int $id) : void
    {
        try {
            $exists = $this->rolesRepo->delete($id);
            if (!$exists) {
                $isSystem = $this->rolesRepo->checkSystem($id);
                if ($isSystem === NULL)
                    throw new NotFoundException($this->i18n->t('errors.common.not_found'),ErrorCodes::NOT_FOUND);
                else if ($isSystem)
                    throw new ConflictException($this->i18n->t("errors.roles.system_role_delete"), ErrorCodes::SYSTEM_PART);
                else throw new HttpException('Unknown', 500);
            }
        } catch (PDOException $e) {
            throw $this->translator->translate($e);
        }
    }

    private function requireDefaultLanguage(array $translations, ErrorBag $bag) : void
    {
        $defaultLang = $this->settings->i18n->default;
        
        if (!isset($translations[$defaultLang])) {
            $bag->add('translations.' . $defaultLang, [$this->i18n->t('errors.common.required')]);
        }
    }
}