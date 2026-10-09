<?php
declare(strict_types=1);

namespace App\Validators\Auth;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

/**
 * @extends Validator<AccountCreditsForm>
 */
final class AccountCreditsFormValidator extends Validator 
{
    public function __construct(Translator $translator)
    {
        parent::__construct($translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;
        
        if (!preg_match('#^[A-Za-z0-9_\.-]{3,}$#', $item->username))
        {
            $bad->add('username', [$this->translator->t("errors.account.username_format")]);
            $ok = false;
        }

        if (!filter_var($item->email, FILTER_VALIDATE_EMAIL))
        {
            $bad->add('email', [$this->translator->t("errors.account.email_format")]);
            $ok = false;
        }

        return $ok;
    }
}