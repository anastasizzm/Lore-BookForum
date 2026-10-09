<?php
declare(strict_types=1);

namespace App\Validators\Auth;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

/**
 * @extends Validator<RegisterForm>
 */
final class RegisterFormValidator extends Validator 
{
    public function __construct(Translator $translator)
    {
        parent::__construct($translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;
        
        if (!preg_match('#^[A-Za-z0-9_\.-]{3,}$#', $item->username)) {
            $bag->add('username', [$this->translator->t('errors.common.format')]);
            $ok = false;
        }

        if (!filter_var($item->email, FILTER_VALIDATE_EMAIL)) {
            $bag->add('email', [$this->translator->t('errors.common.format')]);
            $ok = false;
        }

        if ($item->name === '') {
            $bag->add('name', [$this->translator->t('errors.common.required')]);
            $ok = false;
        }

        if ($item->surname === '') {
            $bag->add('surname', [$this->translator->t('errors.common.required')]);
            $ok = false;
        }

        if (strlen($item->password) < 8) {
            $bag->add('password', [$this->translator->t('errors.common.min', [':value' => 8])]);
            $ok = false;
        }

        return $ok;
    }
}