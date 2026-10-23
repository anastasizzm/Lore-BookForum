<?php
declare(strict_types=1);

namespace App\Validators\Additionals;

use App\Validators\Validator;
use App\Validators\TranslationsValidator;
use App\Forms\Translations\Limits\Additionals\TypeFormLimits;
use App\Lib\I18n\Translator;
use App\Lib\Settings\Settings;
use App\Models\Errors\ErrorBag;

final class TypeFormValidator extends Validator
{
    private readonly TypeFormLimits $limits;

    public function __construct(
        Settings $settings,
        Translator $translator,
        private readonly TranslationsValidator $translationsValidator
    ){
        parent::__construct($settings, $translator);
        $this->limits = new TypeFormLimits();
    }

    public function validate(mixed $item, ErrorBag $bag) : bool
    {
        $defaultLang = $this->settings->i18n->default;
        $ok = true;

        if (isset($item->translations[$defaultLang]) && trim((string)$item->translations[$defaultLang]) === '') {
            $bag->add('translations.' . $defaultLang, [$this->translator->t('errors.common.required')]);
            $ok = false;
        }

        $translationOk = $this->translationsValidator->validateTranslations(
            $item->translations,
            $this->limits,
            'translations',
            $bag
        );

        return $ok && $translationOk;
    }
}