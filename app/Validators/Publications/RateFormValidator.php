<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;
use App\Lib\Settings\Settings;

/**
 * @extends Validator<RateForm>
 */
final class RateFormValidator extends Validator
{
    public const array ALLOWED_VALUES = [1,2,3,4,5];

    public function __construct(Settings $settings, Translator $translator)
    {}

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;

        if (!in_array($item->rating, self::ALLOWED_VALUES, true))
        {
            $bag->add('rating', [$this->translator->t('errors.common.not_allowed')]);
            $ok = false;
        }

        return $ok;
    }
}