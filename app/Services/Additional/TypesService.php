<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\TypesRepository;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\TypeForm;
use App\Validators\Additionals\TypeFormValidator;
use App\Lib\Settings\Settings;
use App\Lib\I18n\Translator as I18nTranslator;
use App\Extensions\EnumExtensions;
use App\Models\PaginatedList;
use App\Models\BasicModel;
use App\Models\Enums\BasicModelSortBy;
use App\Exceptions\Translators\TypeExceptionTranslator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Models\Errors\ErrorBag;
use App\ErrorCodes;
use PDOException;

final class TypesService
{
    public function __construct(
        private readonly TypesRepository $typesRepo,
        private readonly TypeFormValidator $validator,
        private readonly TypeExceptionTranslator $translator,
        private readonly Settings $settings,
        private readonly I18nTranslator $i18n
    ){}

    public function getList(BasicModelListQuery $query) : PaginatedList
    {
        $page = $query->pagination->page();
        $pageSize = $query->pagination->pageSize();

        $sortEnum = ($query->sort->hasData()
            ? EnumExtensions::tryResolve(BasicModelSortBy::class, $query->sort->sortString())
            : null) ?? BasicModelSortBy::Alphabet;

        $items = $this->typesRepo->getList(
            $page, 
            $pageSize, 
            $sortEnum,
            $query->search,
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function retrieve(int $typeId) : BasicModel 
    {
        $model = $this->typesRepo->retrieve($typeId);
        if ($model === NULL) throw new NotFoundException($this->i18n->t('errors.common.not_found'), ErrorCodes::NOT_FOUND);
        return $model;
    }

    public function create(TypeForm $form) : int
    {
        $bag = new ErrorBag();
        $this->requireDefaultLanguage($form->translations, $bag);
        $this->validator->validate($form, $bag);

        if (!$bag->isEmpty()) {
            throw new ValidationException($bag->all());
        }

        try {
            return $this->typesRepo->create($form->translations);
        } catch (PDOException $e) {
            throw $this->translator->translate($e);
        }
    }

    public function update(int $id, TypeForm $form) : void
    {
        $bag = new ErrorBag();
        $this->requireDefaultLanguage($form->translations, $bag);
        $this->validator->validate($form, $bag);

        if (!$bag->isEmpty()) {
            throw new ValidationException($bag->all());
        }

        try {
            $exists = $this->typesRepo->update($id, $form->translations);

            if (!$exists) {
                throw new NotFoundException(
                    $this->i18n->t('errors.common.not_found'),
                    ErrorCodes::NOT_FOUND
                );
            }
        } catch (PDOException $e) {
            throw $this->translator->translate($e);
        }
    }

    public function delete(int $id) : void
    {
        try {
            $exists = $this->typesRepo->delete($id);
            if (!$exists) {
                throw new NotFoundException($this->i18n->t('errors.common.not_found'),ErrorCodes::NOT_FOUND);
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