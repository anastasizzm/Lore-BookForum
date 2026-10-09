<?php
declare(strict_types=1);

namespace App\Validators\Auth;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

/**
 * @extends Validator<LoginForm>
 */
final class LoginFormValidator extends Validator 
{
    public function __construct(Translator $translator)
    {
        parent::__construct($translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;
        if ($item->login === '') {
            $bag->add('login', [$this->translator->t("errors.common.required")]);
            $ok = false;
        }

        if (strlen($item->password) < 8) {
            $bag->add('password', [$this->translator->t("errors.common.min_len", [":value" => 8])]);
            $ok = false;
        }

        return $ok;
    }
}