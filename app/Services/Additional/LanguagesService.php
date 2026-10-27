<?php
declare(strict_types=1);

namespace App\Services\Additional;

use App\Repositories\Additional\LanguagesRepository;
use App\Models\Queries\PaginationQuery;
use App\Models\PaginatedList;
use App\Validators\Additionals\LanguageFormValidator;
use App\Forms\Additionals\LanguageForm;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Lib\I18n\Translator;
use App\Exceptions\Translators\LanguageExceptionTranslator;
use App\ErrorCodes;

final class LanguagesService
{
    public function __construct(
        private readonly LanguagesRepository $langRepo,
        private readonly LanguageFormValidator $formValidator,
        private readonly Translator $translator,
        private readonly LanguageExceptionTranslator $exceptionTranslator
    ){}

    public function getList(PaginationQuery $query) : PaginatedList
    {
        $page = $query->page();
        $pageSize = $query->pageSize();

        $items = $this->langRepo->getList(
            $page, 
            $pageSize, 
        );

        return PaginatedList::fromArray($items, $page, $pageSize);
    }

    public function retrieve(string $code) : Language 
    {
        $model = $this->langRepo->retrieve($code);
        if ($model === NULL)
            throw new NotFoundException($this->translator->t("errors.common.not_found"), ErrorCodes::NOT_FOUND);
    }

    public function create(LanguageForm $form) : string 
    {
        $bag = $this->formValidator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());

        try{
            return $this->langRepo->create($form->code);
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function update(string $code, LanguageForm $form) : void 
    {
        $bag = $this->formValidator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());

        try{
            $exists = $this->langRepo->update($code, $form->code);
            if (!$exists) throw new NotFoundException($this->translator->t("errors.common.not_found"), ErrorCodes::NOT_FOUND);
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function delete(string $code) : void 
    {
        try{
            $exists = $this->langRepo->delete($code);
            if (!$exists) throw new NotFoundException($this->translator->t("errors.common.not_found"), ErrorCodes::NOT_FOUND);
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }
    }
}