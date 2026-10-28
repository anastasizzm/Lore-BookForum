<?php
declare(strict_types=1);

namespace App\Validators\Additionals;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Lib\Settings\Settings;
use App\Models\Errors\ErrorBag;

final class LanguageFormValidator extends Validator
{
    private const string CODE_REGEX = "#^[A-Za-z]{2}$#";

    public function __construct(
        Settings $settings,
        Translator $translator,
    ){
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool
    {
        $ok = true;
        if (!preg_match(self::CODE_REGEX, $item->code, $m))
        {
            $bag->add('code', [$this->translator->t("errors.common.format")]);
            $ok = false;
        }

        return $ok;
    }
}