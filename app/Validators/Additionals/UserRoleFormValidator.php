<?php
declare(strict_types=1);

namespace App\Validators\Additionals;

use App\Validators\Validator;
use App\Validators\TranslationsValidator;
use App\Forms\Translations\Limits\Additionals\UserRoleFormLimits;
use App\Lib\I18n\Translator;
use App\Lib\Settings\Settings;
use App\Models\Errors\ErrorBag;

final class UserRoleFormValidator extends Validator
{
    private const COLOR_REGEX = "#^[A-Z0-9]{6}$#";

    private readonly UserRoleFormLimits $limits;

    public function __construct(
        Settings $settings,
        Translator $translator,
        private readonly TranslationsValidator $translationsValidator
    ){
        parent::__construct($settings, $translator);
        $this->limits = new UserRoleFormLimits();
    }

    public function validate(mixed $item, ErrorBag $bag) : bool
    {
        $ok = $this->translationsValidator->validateTranslations(
            $item->translations,
            $this->limits,
            'translations',
            $bag
        );

        if (!preg_match(self::COLOR_REGEX, $item->color, $m))
        {
            $bag->add('color', [$this->translator->t('errors.common.format')]);
            $ok = false;
        }

        return $ok;
    }
}