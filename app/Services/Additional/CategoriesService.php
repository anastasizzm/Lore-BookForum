<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\CategoriesRepository;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\CategoryForm;
use App\Validators\Additionals\CategoryFormValidator;
use App\Lib\Settings\Settings;
use App\Lib\I18n\Translator as I18nTranslator;
use App\Extensions\EnumExtensions;
use App\Models\PaginatedList;
use App\Models\Enums\BasicModelSortBy;
use App\Exceptions\Translators\CategoryExceptionTranslator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\ErrorCodes;
use PDOException;

final class CategoriesService
{
    public function __construct(
        private readonly CategoriesRepository $categoriesRepo,
        private readonly CategoryFormValidator $validator,
        private readonly CategoryExceptionTranslator $translator,
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

        $items = $this->categoriesRepo->getList(
            $page, 
            $pageSize, 
            $sortEnum,
            $query->search,
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function create(CategoryForm $form) : int
    {
        $defaultLang = $this->settings->i18n->default;
        
        if (!isset($form->translations[$defaultLang])) {
            throw new ValidationException([
                'translations.' . $defaultLang => [$this->i18n->t('errors.common.required')]
            ]);
        }

        $bag = $this->validator->validateOne($form);
        if (!$bag->isEmpty()) {
            throw new ValidationException($bag->all());
        }

        try {
            return $this->categoriesRepo->create($form->translations);
        } catch (PDOException $e) {
            throw $this->translator->translate($e);
        }
    }

    public function update(int $id, CategoryForm $form) : void
    {
        $bag = $this->validator->validateOne($form);
        if (!$bag->isEmpty()) {
            throw new ValidationException($bag->all());
        }

        try {
            $exists = $this->categoriesRepo->update($id, $form->translations);

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

    public function delete(int $id) : bool
    {
        try {
            return $this->categoriesRepo->delete($id);
        } catch (PDOException $e) {
            throw $this->translator->translate($e);
        }
    }
}