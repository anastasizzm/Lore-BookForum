<?php
declare(strict_types=1);

namespace App\Validators\Additionals;

use App\Validators\Validator;
use App\Validators\TranslationsValidator;
use App\Forms\Translations\Limits\Additionals\GenreFormLimits;
use App\Lib\I18n\Translator;
use App\Lib\Settings\Settings;
use App\Models\Errors\ErrorBag;

final class GenreFormValidator extends Validator
{
    private readonly GenreFormLimits $limits;

    public function __construct(
        Settings $settings,
        Translator $translator,
        private readonly TranslationsValidator $translationsValidator
    ){
        parent::__construct($settings, $translator);
        $this->limits = new GenreFormLimits();
    }

    public function validate(mixed $item, ErrorBag $bag) : bool
    {
        return $this->translationsValidator->validateTranslations(
            $item->translations,
            $this->limits,
            'translations',
            $bag
        );
    }
}