<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\GenresRepository;
use App\Models\Queries\Additional\BasicModelListQuery;
use App\Forms\Translations\Additionals\GenreForm;
use App\Validators\Additionals\GenreFormValidator;
use App\Lib\Settings\Settings;
use App\Lib\I18n\Translator as I18nTranslator;
use App\Extensions\EnumExtensions;
use App\Models\PaginatedList;
use App\Models\Enums\BasicModelSortBy;
use App\Exceptions\Translators\GenreExceptionTranslator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Models\Errors\ErrorBag;
use App\ErrorCodes;
use PDOException;

final class GenresService
{
    public function __construct(
        private readonly GenresRepository $genresRepo,
        private readonly GenreFormValidator $validator,
        private readonly GenreExceptionTranslator $translator,
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

        $items = $this->genresRepo->getList(
            $page, 
            $pageSize, 
            $sortEnum,
            $query->search,
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function create(GenreForm $form) : int
    {
        $bag = new ErrorBag();
        $this->requireDefaultLanguage($form->translations, $bag);
        $this->validator->validate($form, $bag);

        if (!$bag->isEmpty()) {
            throw new ValidationException($bag->all());
        }

        try {
            return $this->genresRepo->create($form->translations);
        } catch (PDOException $e) {
            throw $this->translator->translate($e);
        }
    }

    public function update(int $id, GenreForm $form) : void
    {
        $bag = new ErrorBag();
        $this->requireDefaultLanguage($form->translations, $bag);
        $this->validator->validate($form, $bag);

        if (!$bag->isEmpty()) {
            throw new ValidationException($bag->all());
        }

        try {
            $exists = $this->genresRepo->update($id, $form->translations);

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
            return $this->genresRepo->delete($id);
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