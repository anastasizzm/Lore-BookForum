<?php
declare(strict_types=1);

namespace App\Validators\Users;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

/**
 * @extends Validator<ProfileForm>
 */
final class ProfileFormValidator extends Validator 
{
    public function __construct(Translator $translator)
    {
        parent::__construct($translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;

        if (empty($item->avatar))
        {
            $bag->add('avatar', [$this->translator->t('errors.common.required')]);
            $ok = false;
        }

        if (empty($item->name))
        {
            $bag->add('name', [$this->translator->t('errors.common.required')]);
            $ok = false;
        }

        if (empty($item->surname))
        {
            $bag->add('surname', [$this->translator->t('errors.common.required')]);
            $ok = false;
        }

        return $ok;
    }
}